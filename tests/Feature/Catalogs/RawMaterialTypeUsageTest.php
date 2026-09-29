<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use App\Models\UnitOfMeasure;
use Inertia\Testing\AssertableInertia;

// El tipo de insumo de la categoría decide dónde se ofrece cada materia prima (decisión del 2026-09-29): las fórmulas
// solo usan químicos y el envase de una presentación debe ser de tipo Envase (B40), sin importar el nombre de la
// categoría. Las categorías inactivas se tratan como las unidades: nuevas no, la actual se conserva.

beforeEach(function () {
    $this->admin = actingAsRole(SystemRole::Admin);
    $this->unit = UnitOfMeasure::factory()->create();
    $this->chemical = RawMaterial::factory()->create(['code' => 'RESINA-1']);
    $this->uncategorized = RawMaterial::factory()->create(['code' => 'SIN-CAT', 'category_id' => null]);
    $this->container = RawMaterial::factory()->create([
        'code' => 'CUNETE-5',
        'category_id' => RawMaterialCategory::factory()->container()->create(['name' => 'Recipientes'])->id,
    ]);
    $this->label = RawMaterial::factory()->create([
        'code' => 'ETQ-1',
        'category_id' => RawMaterialCategory::factory()->label()->create()->id,
    ]);
});

/**
 * @return array<string, mixed>
 */
function typeUsageFormulaPayload(Product $product, RawMaterial $material, UnitOfMeasure $unit): array
{
    return [
        'product_id' => $product->id,
        'details' => [
            ['raw_material_id' => $material->id, 'quantity' => 2, 'unit_of_measure_id' => $unit->id],
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function typeUsageVariantPayload(UnitOfMeasure $unit, ?RawMaterial $package, string $code): array
{
    return [
        'code' => $code,
        'name' => 'Presentación de prueba',
        'unit_of_measure_id' => $unit->id,
        'presentation_value' => 1,
        'presentation_label' => 'Galón',
        'package_raw_material_id' => $package?->id,
        'is_active' => true,
    ];
}

it('ofrece como ingredientes de fórmula solo materias primas de tipo Químico o sin categoría', function () {
    $this->get(route('formulas.create'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('rawMaterials', fn ($materials) => ($ids = collect($materials)->pluck('id'))
            ->contains($this->chemical->id)
            && $ids->contains($this->uncategorized->id)
            && ! $ids->contains($this->container->id)
            && ! $ids->contains($this->label->id)));
});

it('rechaza un envase o una etiqueta como ingrediente de una fórmula', function (string $material) {
    $product = Product::factory()->create();

    $this->post(route('formulas.store'), typeUsageFormulaPayload($product, $this->{$material}, $this->unit))
        ->assertSessionHasErrors('details.0.raw_material_id');
})->with(['container', 'label']);

it('acepta un químico como ingrediente de una fórmula', function () {
    $product = Product::factory()->create();

    $this->post(route('formulas.store'), typeUsageFormulaPayload($product, $this->chemical, $this->unit))
        ->assertSessionHasNoErrors();
});

it('exige que el envase de una presentación sea de tipo Envase, se llame como se llame su categoría', function () {
    $product = Product::factory()->create();

    // Una categoría química llamada «Envases…» ya no cuenta como envase: decide el tipo, no el nombre.
    $misnamed = RawMaterial::factory()->create([
        'category_id' => RawMaterialCategory::factory()->create(['name' => 'Envases viejos'])->id,
    ]);

    $this->post(route('products.variants.store', $product), typeUsageVariantPayload($this->unit, $misnamed, '11111111'))
        ->assertSessionHasErrors(['package_raw_material_id' => 'La materia prima elegida no es de tipo envase.']);

    $this->post(route('products.variants.store', $product), typeUsageVariantPayload($this->unit, $this->container, '22222222'))
        ->assertSessionHasNoErrors();

    $variant = ProductVariant::query()->where('code', '22222222')->sole();

    $this->patch(route('products.variants.update', [$product, $variant]), typeUsageVariantPayload($this->unit, $this->chemical, '22222222'))
        ->assertSessionHasErrors('package_raw_material_id');
});

it('rechaza una categoría inactiva al crear una materia prima y la conserva al editarla', function () {
    $inactive = RawMaterialCategory::factory()->create(['is_active' => false]);
    $payload = fn (string $code, int $categoryId) => [
        'code' => $code,
        'category_id' => $categoryId,
        'unit_of_measure_id' => $this->unit->id,
        'minimum_stock' => 10,
        'alert_days_before_expiry' => 30,
        'is_active' => true,
    ];

    $this->post(route('raw-materials.store'), $payload('MP-NUEVA', $inactive->id))
        ->assertSessionHasErrors('category_id');

    $material = RawMaterial::factory()->create(['category_id' => $inactive->id, 'unit_of_measure_id' => $this->unit->id]);

    $this->put(route('raw-materials.update', $material), $payload($material->code, $inactive->id))
        ->assertSessionHasNoErrors();
});

it('rechaza una categoría de producto inactiva al crear un producto y la conserva al editarlo', function () {
    $inactive = ProductCategory::factory()->create(['is_active' => false]);
    $payload = fn (?Product $product) => [
        'code' => $product?->code,
        'name' => $product?->name ?? 'Esmalte de prueba',
        'brand' => 'BEPRO',
        'category_id' => $inactive->id,
        'unit_of_measure_id' => $product?->unit_of_measure_id ?? $this->unit->id,
        'cif_percentage' => 10,
        'price_threshold' => 3,
    ];

    $this->post(route('products.store'), $payload(null))->assertSessionHasErrors('category_id');

    $product = Product::factory()->create(['category_id' => $inactive->id]);

    $this->put(route('products.update', $product), $payload($product))->assertSessionHasNoErrors();
});

it('ofrece solo categorías activas al crear y suma la actual al editar', function () {
    $inactive = RawMaterialCategory::factory()->create(['is_active' => false]);
    $material = RawMaterial::factory()->create(['category_id' => $inactive->id]);

    $this->get(route('raw-materials.create'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('categories', fn ($categories) => ! collect($categories)->pluck('id')->contains($inactive->id)));

    $this->get(route('raw-materials.edit', $material))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('categories', fn ($categories) => collect($categories)->pluck('id')->contains($inactive->id)));
});

it('muestra el tipo de insumo que hereda la materia prima de su categoría', function () {
    // El formulario lo muestra en cuanto se elige la categoría (campo de solo lectura).
    $this->get(route('raw-materials.create'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('categories', fn ($categories) => ($option = collect($categories)->firstWhere('id', $this->container->category_id))
            && $option['type'] === 'container'
            && $option['type_label'] === 'Envase'));

    $this->get(route('raw-materials.index', ['search' => 'CUNETE-5']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('rawMaterials.data.0.category.type_label', 'Envase'));

    $this->get(route('raw-materials.show', $this->label))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rawMaterial.category.type', 'label')
            ->where('rawMaterial.category.type_label', 'Etiqueta'));
});

/**
 * @return array<string, mixed>
 */
function typeUsageMaterialPayload(RawMaterial $material, RawMaterialCategory $category): array
{
    return [
        'code' => $material->code,
        'category_id' => $category->id,
        'unit_of_measure_id' => $material->unit_of_measure_id,
        'minimum_stock' => 10,
        'alert_days_before_expiry' => 30,
        'is_active' => true,
    ];
}

it('no deja pasar a otro tipo un envase que usa alguna presentación, pero sí a otra categoría de envases', function () {
    ProductVariant::factory()->count(2)->create(['package_raw_material_id' => $this->container->id]);
    $chemicals = RawMaterialCategory::factory()->create();
    $otherContainers = RawMaterialCategory::factory()->container()->create();

    $this->put(route('raw-materials.update', $this->container), typeUsageMaterialPayload($this->container, $chemicals))
        ->assertSessionHasErrors(['category_id' => 'No se puede pasar a una categoría de tipo químico: esta materia prima es el envase de 2 presentaciones.']);

    $this->put(route('raw-materials.update', $this->container), typeUsageMaterialPayload($this->container, $otherContainers))
        ->assertSessionHasNoErrors();

    expect($this->container->fresh()->category_id)->toBe($otherContainers->id);
});

it('no deja pasar a otro tipo un químico que está en alguna fórmula', function () {
    FormulaDetail::create([
        'formula_id' => Formula::factory()->create()->id,
        'raw_material_id' => $this->chemical->id,
        'quantity' => 1,
        'unit_of_measure_id' => $this->unit->id,
        'step_order' => 1,
    ]);
    $containers = RawMaterialCategory::factory()->container()->create();

    $this->put(route('raw-materials.update', $this->chemical), typeUsageMaterialPayload($this->chemical, $containers))
        ->assertSessionHasErrors(['category_id' => 'No se puede pasar a una categoría de tipo envase: esta materia prima está en 1 línea de fórmula.']);
});

it('deja pasar a otro tipo una materia prima que nada usa, para corregir una mala clasificación', function () {
    $labels = RawMaterialCategory::factory()->label()->create();

    $this->put(route('raw-materials.update', $this->chemical), typeUsageMaterialPayload($this->chemical, $labels))
        ->assertSessionHasNoErrors();

    expect($this->chemical->fresh()->category_id)->toBe($labels->id);
});
