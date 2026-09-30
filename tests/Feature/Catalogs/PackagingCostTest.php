<?php

declare(strict_types=1);

use App\Enums\RawMaterialType;
use App\Enums\SystemRole;
use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\ProductionCost;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ProductionCostRecalculationService;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

// 3.7, PR A: la presentación cuesta (granel × galones + envase + etiqueta) × (1 + CIF), y cambiar el precio del envase o
// de la etiqueta recalcula solo las presentaciones que los usan (decisiones del 2026-09-29 y 2026-09-30).

beforeEach(function () {
    $this->admin = actingAsRole(SystemRole::Admin);
    $this->unit = UnitOfMeasure::factory()->create();

    $this->container = RawMaterial::factory()->create([
        'code' => 'GALON-MET',
        'category_id' => RawMaterialCategory::factory()->container()->create()->id,
        'current_price' => '2000',
    ]);
    $this->labelCategory = RawMaterialCategory::factory()->label()->create();
    $this->label = RawMaterial::factory()->withoutInventoryTracking()->create([
        'code' => 'ETQ-GALON',
        'category_id' => $this->labelCategory->id,
        'unit_of_measure_id' => $this->unit->id,
        'current_price' => '300',
    ]);

    // Sin fórmula activa: su granel cuesta 10.000 por galón y así queda.
    $this->product = Product::factory()->create(['current_cost' => '10000', 'cif_percentage' => '10', 'price_threshold' => '0']);
    $this->variant = ProductVariant::factory()->create([
        'product_id' => $this->product->id,
        'presentation_value' => 1,
        'package_raw_material_id' => $this->container->id,
        'label_raw_material_id' => $this->label->id,
        'current_cost' => null,
        'current_price' => null,
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function packagingMaterialPayload(RawMaterial $material, array $overrides = []): array
{
    return array_merge([
        'code' => $material->code,
        'category_id' => $material->category_id,
        'unit_of_measure_id' => $material->unit_of_measure_id,
        'minimum_stock' => 0,
        'alert_days_before_expiry' => 0,
        'is_active' => true,
    ], $overrides);
}

it('suma el envase y la etiqueta al costo de la presentación y aplica el CIF', function () {
    app(ProductionCostRecalculationService::class)->repriceVariantsOfProduct($this->product, forcePriceRefresh: true);

    // 10.000 × 1 + 2.000 + 300 = 12.300; × 1,10 = 13.530.
    expect($this->variant->fresh())
        ->current_cost->toBe('12300.0000')
        ->current_price->toBe('13530.0000');
});

it('recalcula la presentación con fórmula al recalcular su producto, con la etiqueta incluida', function () {
    $chemical = RawMaterial::factory()->create(['current_price' => '4000']);
    $formula = Formula::factory()->create(['product_id' => $this->product->id]);
    FormulaDetail::create(['formula_id' => $formula->id, 'raw_material_id' => $chemical->id, 'quantity' => 2, 'unit_of_measure_id' => $chemical->unit_of_measure_id, 'step_order' => 1]);

    app(ProductionCostRecalculationService::class)->recalculateForProduct($this->product->id);

    // Granel 2 × 4.000 = 8.000; presentación 8.000 + 2.000 + 300.
    expect($this->variant->fresh()->current_cost)->toBe('10300.0000');
});

it('recalcula solo las presentaciones que usan el envase cuando cambia su precio, sin tocar el historial del granel', function () {
    $other = ProductVariant::factory()->create(['product_id' => $this->product->id, 'package_raw_material_id' => null, 'current_cost' => '777']);
    $this->container->update(['current_price' => '2500']);

    app(ProductionCostRecalculationService::class)->recalculateForRawMaterial($this->container->id);

    expect($this->variant->fresh()->current_cost)->toBe('12800.0000')
        ->and($other->fresh()->current_cost)->toBe('777.0000')
        ->and(ProductionCost::query()->where('product_id', $this->product->id)->exists())->toBeFalse();
});

it('guarda el precio escrito a mano de una etiqueta y recalcula las presentaciones que la usan', function () {
    $this->put(route('raw-materials.update', $this->label), packagingMaterialPayload($this->label, ['tracks_inventory' => false, 'current_price' => '450']))
        ->assertSessionHasNoErrors();

    expect($this->label->fresh())
        ->current_price->toBe('450.0000')
        ->previous_price->toBe('300.0000')
        ->and($this->variant->fresh()->current_cost)->toBe('12450.0000');
});

it('no acepta precio escrito a mano en una materia prima que controla inventario', function () {
    $this->put(route('raw-materials.update', $this->container), packagingMaterialPayload($this->container, ['current_price' => '9999']))
        ->assertSessionHasErrors(['current_price' => 'Esta materia prima controla inventario: su precio sale de sus compras y no se escribe a mano.']);

    $this->post(route('raw-materials.store'), packagingMaterialPayload($this->container, ['code' => 'NUEVO-ENV', 'current_price' => '10']))
        ->assertSessionHasErrors('current_price');

    expect($this->container->fresh()->current_price)->toBe('2000.0000');
});

it('solo deja fijar el precio a quien puede modificar parámetros de costo', function () {
    // Puede editar materias primas, pero no costos: ningún rol del sistema es así, se arma a mano.
    $role = Role::create(['name' => 'Bodega']);
    $role->givePermissionTo(['dashboard.view', 'raw_materials.view', 'raw_materials.edit']);
    $warehouseKeeper = User::factory()->create();
    $warehouseKeeper->assignRole($role);

    $this->actingAs($warehouseKeeper)
        ->put(route('raw-materials.update', $this->label), packagingMaterialPayload($this->label, ['current_price' => null]))
        ->assertSessionHasErrors(['current_price' => 'No tienes permiso para fijar el precio de una materia prima.']);

    // Sin enviar el precio sí puede editar el resto, y el precio se conserva.
    $this->actingAs($warehouseKeeper)
        ->put(route('raw-materials.update', $this->label), packagingMaterialPayload($this->label, ['minimum_stock' => 5]))
        ->assertSessionHasNoErrors();

    expect($this->label->fresh())
        ->current_price->toBe('300.0000')
        ->minimum_stock->toBe('5.0000');
});

it('crea una materia prima sin control de inventario con su precio', function () {
    $this->post(route('raw-materials.store'), packagingMaterialPayload($this->label, ['code' => 'ETQ-CUNETE', 'tracks_inventory' => false, 'current_price' => '800']))
        ->assertSessionHasNoErrors();

    expect(RawMaterial::query()->where('code', 'ETQ-CUNETE')->sole())
        ->tracks_inventory->toBeFalse()
        ->current_price->toBe('800.0000');
});

it('no deja quitar el control de inventario con saldo en bodega, pero sí sin saldo, y activarlo siempre', function () {
    InventoryBatch::factory()->create([
        'raw_material_id' => $this->container->id,
        'warehouse_id' => Warehouse::factory()->create()->id,
        'initial_quantity' => '10',
        'remaining_quantity' => '4',
    ]);

    $this->put(route('raw-materials.update', $this->container), packagingMaterialPayload($this->container, ['tracks_inventory' => false]))
        ->assertSessionHasErrors('tracks_inventory');

    InventoryBatch::query()->where('raw_material_id', $this->container->id)->update(['remaining_quantity' => 0]);

    $this->put(route('raw-materials.update', $this->container), packagingMaterialPayload($this->container, ['tracks_inventory' => false]))
        ->assertSessionHasNoErrors();
    $this->put(route('raw-materials.update', $this->label), packagingMaterialPayload($this->label, ['tracks_inventory' => true]))
        ->assertSessionHasNoErrors();

    expect($this->container->fresh()->tracks_inventory)->toBeFalse()
        ->and($this->label->fresh()->tracks_inventory)->toBeTrue();
});

it('exige que la etiqueta habitual de una presentación sea de tipo Etiqueta y activa, y conserva la inactiva que ya tiene', function () {
    $payload = fn (?RawMaterial $label, string $code) => [
        'code' => $code,
        'name' => 'Galón',
        'unit_of_measure_id' => $this->unit->id,
        'presentation_value' => 1,
        'label_raw_material_id' => $label?->id,
    ];

    $this->post(route('products.variants.store', $this->product), $payload($this->container, '11111111'))
        ->assertSessionHasErrors(['label_raw_material_id' => 'La materia prima elegida no es de tipo etiqueta.']);
    $this->post(route('products.variants.store', $this->product), $payload($this->label, '22222222'))
        ->assertSessionHasNoErrors();

    $this->label->update(['is_active' => false]);

    $this->post(route('products.variants.store', $this->product), $payload($this->label, '33333333'))
        ->assertSessionHasErrors('label_raw_material_id');
    $this->patch(route('products.variants.update', [$this->product, $this->variant]), $payload($this->label, $this->variant->code))
        ->assertSessionHasNoErrors();
});

it('no deja pasar a otro tipo una etiqueta que usa alguna presentación', function () {
    $chemicals = RawMaterialCategory::factory()->create(['type' => RawMaterialType::Chemical]);

    $this->put(route('raw-materials.update', $this->label), packagingMaterialPayload($this->label, ['category_id' => $chemicals->id]))
        ->assertSessionHasErrors(['category_id' => 'No se puede pasar a una categoría de tipo químico: esta materia prima es la etiqueta de 1 presentación.']);
});

it('ofrece en la ficha del producto solo etiquetas activas, más la inactiva que ya usa una presentación', function () {
    $otherLabel = RawMaterial::factory()->create(['category_id' => $this->labelCategory->id]);
    $inactiveLabel = RawMaterial::factory()->inactive()->create(['category_id' => $this->labelCategory->id]);
    $this->label->update(['is_active' => false]);

    $this->get(route('products.show', $this->product))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('labelMaterials', fn ($materials) => ($ids = collect($materials)->pluck('id'))
                ->contains($this->label->id)
                && $ids->contains($otherLabel->id)
                && ! $ids->contains($inactiveLabel->id)
                && ! $ids->contains($this->container->id)));
});
