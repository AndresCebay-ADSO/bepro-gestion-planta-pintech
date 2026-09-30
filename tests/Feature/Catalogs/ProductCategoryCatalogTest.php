<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\Product;
use App\Models\ProductCategory;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;

// Catálogo de categorías de producto (3.2), en Configuración → Catálogos.

it('lista las categorías de producto con cuántos productos tienen', function () {
    actingAsRole(SystemRole::SuperAdmin);
    $category = ProductCategory::factory()->create(['name' => 'Esmaltes']);
    Product::factory()->count(3)->create(['category_id' => $category->id]);

    $this->get(route('catalogs.product-categories.index'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Settings/Catalogs/ProductCategories/Index')
            ->where('categories.data', fn ($rows) => collect($rows)->firstWhere('id', $category->id)['products_count'] === 3));
});

it('deja a Admin consultar las categorías de producto pero no modificarlas', function () {
    actingAsRole(SystemRole::Admin);
    $category = ProductCategory::factory()->create();

    $this->get(route('catalogs.product-categories.index'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can.create', false));
    $this->post(route('catalogs.product-categories.store'), ['name' => 'Nueva'])->assertForbidden();
    $this->put(route('catalogs.product-categories.update', $category), ['name' => 'Otra'])->assertForbidden();
    $this->delete(route('catalogs.product-categories.destroy', $category))->assertForbidden();
});

it('crea una categoría de producto, la audita y no admite nombres repetidos', function () {
    actingAsRole(SystemRole::SuperAdmin);

    $this->post(route('catalogs.product-categories.store'), ['name' => '  Impermeabilizantes ', 'description' => ''])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('catalogs.product-categories.index'));

    $category = ProductCategory::query()->where('name', 'Impermeabilizantes')->sole();

    expect($category->is_active)->toBeTrue()
        ->and($category->description)->toBeNull()
        ->and(Activity::query()->where('subject_type', ProductCategory::class)->where('subject_id', $category->id)->value('description'))
        ->toBe('Categoría de producto "Impermeabilizantes" creada');

    $this->post(route('catalogs.product-categories.store'), ['name' => 'Impermeabilizantes'])
        ->assertSessionHasErrors('name');
});

it('no admite un nombre repetido aunque cambien las mayúsculas, pero deja cambiar las del propio', function () {
    actingAsRole(SystemRole::SuperAdmin);
    ProductCategory::factory()->create(['name' => 'Esmaltes']);
    $lacquers = ProductCategory::factory()->create(['name' => 'Lacas']);

    $this->post(route('catalogs.product-categories.store'), ['name' => 'ESMALTES'])
        ->assertSessionHasErrors(['name' => 'El campo nombre ya se encuentra registrado.']);
    $this->put(route('catalogs.product-categories.update', $lacquers), ['name' => 'esmaltes'])
        ->assertSessionHasErrors('name');
    $this->put(route('catalogs.product-categories.update', $lacquers), ['name' => 'LACAS'])
        ->assertSessionHasNoErrors();

    expect(ProductCategory::query()->count())->toBe(2)
        ->and($lacquers->fresh()->name)->toBe('LACAS');
});

it('desactiva una categoría y conserva el estado si la edición no lo envía', function () {
    actingAsRole(SystemRole::SuperAdmin);
    $category = ProductCategory::factory()->create(['name' => 'Esmaltes']);

    $this->put(route('catalogs.product-categories.update', $category), ['name' => 'Esmaltes', 'is_active' => false])
        ->assertSessionHasNoErrors();
    $this->put(route('catalogs.product-categories.update', $category), ['name' => 'Esmaltes alquídicos'])
        ->assertSessionHasNoErrors();

    expect($category->fresh())
        ->is_active->toBeFalse()
        ->name->toBe('Esmaltes alquídicos');
});

it('elimina una categoría de producto sin productos y rechaza una con productos', function () {
    actingAsRole(SystemRole::SuperAdmin);
    $empty = ProductCategory::factory()->create();
    $used = ProductCategory::factory()->create();
    Product::factory()->create(['category_id' => $used->id]);

    $this->delete(route('catalogs.product-categories.destroy', $empty))
        ->assertRedirect(route('catalogs.product-categories.index'));
    $this->delete(route('catalogs.product-categories.destroy', $used))
        ->assertSessionHas('error', 'La categoría tiene productos. Desactívala en su lugar.');

    expect(ProductCategory::query()->whereKey($empty->id)->exists())->toBeFalse()
        ->and(ProductCategory::query()->whereKey($used->id)->exists())->toBeTrue();
});
