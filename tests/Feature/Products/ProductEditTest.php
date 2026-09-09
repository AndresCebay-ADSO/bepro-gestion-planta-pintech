<?php

declare(strict_types=1);

namespace Tests\Feature\Products;

use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $role = Role::findOrCreate('admin');
    $this->admin = User::factory()->create();
    $this->admin->assignRole($role);

    $this->category = ProductCategory::factory()->create(['name' => 'Pinturas Test']);
    $this->uom = UnitOfMeasure::factory()->create(['code' => 'GAL', 'name' => 'Galón', 'symbol' => 'gal']);
    $this->product = Product::factory()->create([
        'code' => 'P-TEST-01',
        'name' => 'Pintura Test',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->uom->id,
        'cif_percentage' => 25,
        'price_threshold' => 3,
    ]);
});

test('product edit page provides hasActiveFormula as false when no active formula exists', function (): void {
    actingAs($this->admin)
        ->get(route('products.edit', $this->product))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Products/Edit')
            ->has('hasActiveFormula')
            ->where('hasActiveFormula', false)
        );
});

test('product edit page provides hasActiveFormula as true when an active formula exists', function (): void {
    Formula::factory()->create([
        'product_id' => $this->product->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $this->admin->id,
    ]);

    actingAs($this->admin)
        ->get(route('products.edit', $this->product))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Products/Edit')
            ->has('hasActiveFormula')
            ->where('hasActiveFormula', true)
        );
});

test('product update ignores manual current_cost and current_price sent in request', function (): void {
    $this->product->updateQuietly([
        'current_cost' => 10.0,
        'current_price' => 12.5,
    ]);

    actingAs($this->admin)
        ->put(route('products.update', $this->product), [
            'code' => $this->product->code,
            'name' => 'Nombre Actualizado',
            'category_id' => $this->category->id,
            'unit_of_measure_id' => $this->uom->id,
            'cif_percentage' => 25,
            'price_threshold' => 3,
            'current_cost' => 999.99,
            'current_price' => 8888.88,
        ])
        ->assertRedirect(route('products.index'));

    $this->product->refresh();
    expect($this->product->name)->toBe('Nombre Actualizado');
    expect((float) $this->product->current_cost)->toBe(10.0);
    expect((float) $this->product->current_price)->toBe(12.5);
});

test('product variant update ignores manual current_cost and current_price sent in request', function (): void {
    $variant = ProductVariant::factory()->create([
        'product_id' => $this->product->id,
        'code' => 'VAR-01',
        'name' => 'Variante Original',
        'unit_of_measure_id' => $this->uom->id,
        'presentation_value' => 1,
        'presentation_label' => 'Galón',
        'current_cost' => 15.0,
        'current_price' => 18.75,
        'is_active' => true,
    ]);

    actingAs($this->admin)
        ->patch(route('products.variants.update', [$this->product, $variant]), [
            'code' => 'VAR-01',
            'name' => 'Variante Actualizada',
            'unit_of_measure_id' => $this->uom->id,
            'presentation_value' => 1,
            'presentation_label' => 'Galón',
            'current_cost' => 555.55,
            'current_price' => 999.99,
            'is_active' => true,
        ])
        ->assertRedirect(route('products.show', $this->product));

    $variant->refresh();
    expect($variant->name)->toBe('Variante Actualizada');
    expect((float) $variant->current_cost)->toBe(15.0);
    expect((float) $variant->current_price)->toBe(18.75);
});

test('production role user cannot modify cif_percentage or price_threshold', function (): void {
    $produccionRole = Role::findOrCreate('produccion');
    $produccionUser = User::factory()->create();
    $produccionUser->assignRole($produccionRole);

    actingAs($produccionUser)
        ->put(route('products.update', $this->product), [
            'code' => $this->product->code,
            'name' => 'Intento de cambio CIF',
            'category_id' => $this->category->id,
            'unit_of_measure_id' => $this->uom->id,
            'cif_percentage' => 45,
            'price_threshold' => 3,
        ])
        ->assertForbidden();

    $this->product->refresh();
    expect((float) $this->product->cif_percentage)->toBe(25.0);
});

test('admin role user can modify cif_percentage and price_threshold', function (): void {
    actingAs($this->admin)
        ->put(route('products.update', $this->product), [
            'code' => $this->product->code,
            'name' => $this->product->name,
            'category_id' => $this->category->id,
            'unit_of_measure_id' => $this->uom->id,
            'cif_percentage' => 35,
            'price_threshold' => 5,
        ])
        ->assertRedirect(route('products.index'));

    $this->product->refresh();
    expect((float) $this->product->cif_percentage)->toBe(35.0);
    expect((float) $this->product->price_threshold)->toBe(5.0);
});

test('production role user can update product when cif_percentage matches numerically with different formatting', function (): void {
    $produccionRole = Role::findOrCreate('produccion');
    $produccionUser = User::factory()->create();
    $produccionUser->assignRole($produccionRole);

    actingAs($produccionUser)
        ->put(route('products.update', $this->product), [
            'code' => $this->product->code,
            'name' => 'Nombre Actualizado por Producción',
            'category_id' => $this->category->id,
            'unit_of_measure_id' => $this->uom->id,
            'cif_percentage' => 25, // En BD está como '25.00'
            'price_threshold' => 3, // En BD está como '3.00'
        ])
        ->assertRedirect(route('products.index'));

    $this->product->refresh();
    expect($this->product->name)->toBe('Nombre Actualizado por Producción');
});
