<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use App\Models\UnitOfMeasure;
use Inertia\Testing\AssertableInertia;

// Una unidad desactivada no se ofrece para registros nuevos, pero quien ya la usa la conserva al editarse
// (docs/POLITICA_ELIMINACION.md §7). Si el registro la cambia, la nueva debe estar activa.

beforeEach(function () {
    $this->admin = actingAsRole(SystemRole::Admin);
    $this->active = UnitOfMeasure::factory()->create(['name' => 'Activa']);
    $this->inactive = UnitOfMeasure::factory()->create(['name' => 'Inactiva', 'is_active' => false]);
    $this->otherInactive = UnitOfMeasure::factory()->create(['name' => 'Otra inactiva', 'is_active' => false]);
});

/**
 * @return array<string, mixed>
 */
function inactiveUnitRawMaterialPayload(RawMaterial|string $codeOrMaterial, int $unitId): array
{
    $isModel = $codeOrMaterial instanceof RawMaterial;

    return [
        'code' => $isModel ? $codeOrMaterial->code : $codeOrMaterial,
        'category_id' => $isModel ? $codeOrMaterial->category_id : RawMaterialCategory::factory()->create()->id,
        'unit_of_measure_id' => $unitId,
        'minimum_stock' => 10,
        'alert_days_before_expiry' => 30,
        'is_active' => true,
    ];
}

/**
 * @return array<string, mixed>
 */
function inactiveUnitProductPayload(int $unitId, ?Product $product = null): array
{
    return [
        'code' => $product?->code,
        'name' => $product?->name ?? 'Esmalte de prueba',
        'brand' => 'BEPRO',
        'category_id' => $product?->category_id ?? ProductCategory::factory()->create()->id,
        'unit_of_measure_id' => $unitId,
        'cif_percentage' => 10,
        'price_threshold' => 3,
    ];
}

/**
 * @return array<string, mixed>
 */
function inactiveUnitVariantPayload(int $unitId, string $code): array
{
    return [
        'code' => $code,
        'name' => 'Presentación de prueba',
        'unit_of_measure_id' => $unitId,
        'presentation_value' => 1,
        'presentation_label' => 'Galón',
        'is_active' => true,
    ];
}

it('rechaza una unidad inactiva al crear una materia prima', function () {
    $this->post(route('raw-materials.store'), inactiveUnitRawMaterialPayload('MP-INACT', $this->inactive->id))
        ->assertSessionHasErrors('unit_of_measure_id');

    $this->post(route('raw-materials.store'), inactiveUnitRawMaterialPayload('MP-ACT', $this->active->id))
        ->assertSessionHasNoErrors();
});

it('deja a una materia prima conservar su unidad inactiva, pero no cambiar a otra inactiva', function () {
    $material = RawMaterial::factory()->create(['unit_of_measure_id' => $this->inactive->id]);

    $this->put(route('raw-materials.update', $material), inactiveUnitRawMaterialPayload($material, $this->inactive->id))
        ->assertSessionHasNoErrors();

    $this->put(route('raw-materials.update', $material), inactiveUnitRawMaterialPayload($material, $this->otherInactive->id))
        ->assertSessionHasErrors('unit_of_measure_id');

    $this->put(route('raw-materials.update', $material), inactiveUnitRawMaterialPayload($material, $this->active->id))
        ->assertSessionHasNoErrors();

    expect($material->fresh()->unit_of_measure_id)->toBe($this->active->id);
});

it('rechaza una unidad inactiva al crear un producto y la conserva al editarlo', function () {
    $this->post(route('products.store'), inactiveUnitProductPayload($this->inactive->id))
        ->assertSessionHasErrors('unit_of_measure_id');

    $product = Product::factory()->create(['unit_of_measure_id' => $this->inactive->id]);

    $this->put(route('products.update', $product), inactiveUnitProductPayload($this->inactive->id, $product))
        ->assertSessionHasNoErrors();

    $this->put(route('products.update', $product), inactiveUnitProductPayload($this->otherInactive->id, $product))
        ->assertSessionHasErrors('unit_of_measure_id');
});

it('rechaza una unidad inactiva al crear una presentación y la conserva al editarla', function () {
    $product = Product::factory()->create(['unit_of_measure_id' => $this->active->id]);

    $this->post(route('products.variants.store', $product), inactiveUnitVariantPayload($this->inactive->id, '11111111'))
        ->assertSessionHasErrors('unit_of_measure_id');

    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'unit_of_measure_id' => $this->inactive->id]);

    $this->patch(route('products.variants.update', [$product, $variant]), inactiveUnitVariantPayload($this->inactive->id, $variant->code))
        ->assertSessionHasNoErrors();

    $this->patch(route('products.variants.update', [$product, $variant]), inactiveUnitVariantPayload($this->otherInactive->id, $variant->code))
        ->assertSessionHasErrors('unit_of_measure_id');
});

it('ofrece solo unidades activas al crear y suma la actual al editar', function () {
    $material = RawMaterial::factory()->create(['unit_of_measure_id' => $this->inactive->id]);

    $this->get(route('raw-materials.create'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('units', fn ($units) => ! collect($units)->pluck('id')->contains($this->inactive->id)
            && collect($units)->pluck('id')->contains($this->active->id)));

    $this->get(route('raw-materials.edit', $material))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('units', fn ($units) => collect($units)->pluck('id')->contains($this->inactive->id)
            && ! collect($units)->pluck('id')->contains($this->otherInactive->id)));

    $product = Product::factory()->create(['unit_of_measure_id' => $this->active->id]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'unit_of_measure_id' => $this->inactive->id]);

    // En la ficha del producto, el formulario de presentaciones muestra la unidad inactiva que ya usa una de ellas.
    // El estado viaja con cada unidad: el alta de presentaciones solo ofrece las activas.
    $this->get(route('products.show', $product))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('units', fn ($units) => collect($units)->pluck('id')->contains($this->inactive->id)
            && ! collect($units)->pluck('id')->contains($this->otherInactive->id)
            && collect($units)->firstWhere('id', $this->inactive->id)['is_active'] === false));
});
