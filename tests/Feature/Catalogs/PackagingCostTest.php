<?php

declare(strict_types=1);

use App\Actions\RawMaterials\UpdateRawMaterialAction;
use App\Enums\AlertType;
use App\Enums\ProductionOrderStatus;
use App\Enums\RawMaterialType;
use App\Enums\SystemRole;
use App\Models\Alert;
use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\ProductionCost;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\ProductionOrderPackagingPlan;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AlertService;
use App\Services\InventoryService;
use App\Services\ProductionCostRecalculationService;
use App\Services\RawMaterialReferencePriceService;
use App\Services\RawMaterialUsageService;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;
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

    $this->put(route('raw-materials.update', $this->container), packagingMaterialPayload($this->container, ['tracks_inventory' => false, 'current_price' => '2000']))
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

it('rechaza un precio manual que no cabe en la columna, en vez de un error del servidor', function () {
    $this->put(route('raw-materials.update', $this->label), packagingMaterialPayload($this->label, ['current_price' => '123456789']))
        ->assertSessionHasErrors('current_price');

    expect($this->label->fresh()->current_price)->toBe('300.0000');
});

it('costea la presentación con su envase y su etiqueta al guardarla, sin tocar el historial del granel', function () {
    // Con fórmula activa: guardar una presentación no recalcula el granel ni deja un registro sin variación.
    $chemical = RawMaterial::factory()->create(['current_price' => '4000']);
    $formula = Formula::factory()->create(['product_id' => $this->product->id]);
    FormulaDetail::create(['formula_id' => $formula->id, 'raw_material_id' => $chemical->id, 'quantity' => 2, 'unit_of_measure_id' => $chemical->unit_of_measure_id, 'step_order' => 1]);

    $this->post(route('products.variants.store', $this->product), [
        'code' => '44444444',
        'name' => 'Cuñete',
        'unit_of_measure_id' => $this->unit->id,
        'presentation_value' => 5,
        'package_raw_material_id' => $this->container->id,
        'label_raw_material_id' => $this->label->id,
    ])->assertSessionHasNoErrors();

    // Granel del producto: 10.000 por galón. 10.000 × 5 + 2.000 + 300.
    expect(ProductVariant::query()->where('code', '44444444')->sole()->current_cost)->toBe('52300.0000')
        ->and(ProductionCost::query()->where('product_id', $this->product->id)->exists())->toBeFalse();
});

it('no reemplaza con el precio de un lote el precio manual de una materia prima sin control de inventario', function (string $policy) {
    config(['production.raw_material_reference_price_policy' => $policy]);
    // Una compra registrada por error: el lote nunca se descuenta, así que siempre tiene saldo.
    InventoryBatch::factory()->create([
        'raw_material_id' => $this->label->id,
        'warehouse_id' => Warehouse::factory()->create()->id,
        'initial_quantity' => '100',
        'remaining_quantity' => '100',
        'unit_price' => '9000',
    ]);

    expect(app(RawMaterialReferencePriceService::class)->syncRawMaterialCurrentPrice($this->label->id))->toBeFalse()
        ->and($this->label->fresh()->current_price)->toBe('300.0000');
})->with(['conservative_max', 'weighted_average', 'last_lot']);

it('avisa si el precio manual varía más que el umbral, como cuando cambia por compras', function () {
    $this->label->update(['price_variation_threshold' => '10']);

    $this->put(route('raw-materials.update', $this->label), packagingMaterialPayload($this->label, ['current_price' => '3000', 'price_variation_threshold' => '10']))
        ->assertSessionHasNoErrors();

    expect(Alert::query()->where('type', AlertType::VariacionPrecio)->where('raw_material_id', $this->label->id)->exists())->toBeTrue();
});

it('audita el cambio del control de inventario', function () {
    $this->put(route('raw-materials.update', $this->label), packagingMaterialPayload($this->label, ['tracks_inventory' => true]))
        ->assertSessionHasNoErrors();

    $activity = Activity::query()->where('subject_type', RawMaterial::class)->where('subject_id', $this->label->id)->latest('id')->first();

    expect($activity?->causer_id)->toBe($this->admin->id)
        ->and($activity?->properties['old']['tracks_inventory'] ?? null)->toBeFalse()
        ->and($activity?->properties['attributes']['tracks_inventory'] ?? null)->toBeTrue();
});

it('no recalcula las presentaciones de un producto sin costo de granel', function () {
    // 188 de los 202 productos de desarrollo no tienen costo: recalcularlos dejaría el precio en envase + etiqueta.
    $this->product->update(['current_cost' => null]);
    $this->variant->update(['current_cost' => '15000', 'current_price' => '16500']);

    $this->patch(route('products.variants.update', [$this->product, $this->variant]), [
        'code' => $this->variant->code,
        'name' => 'Galón corregido',
        'unit_of_measure_id' => $this->unit->id,
        'presentation_value' => 1,
        'package_raw_material_id' => $this->container->id,
        'label_raw_material_id' => $this->label->id,
    ])->assertSessionHasNoErrors();

    $this->container->update(['current_price' => '2500']);
    app(ProductionCostRecalculationService::class)->recalculateForRawMaterial($this->container->id);

    expect($this->variant->fresh())
        ->name->toBe('Galón corregido')
        ->current_cost->toBe('15000.0000')
        ->current_price->toBe('16500.0000');
});

it('no registra compras ni salidas de una materia prima sin control de inventario, ni la ofrece', function () {
    $warehouse = Warehouse::factory()->create();

    $this->post(route('inventory-movements.store'), [
        'raw_material_id' => $this->label->id,
        'warehouse_id' => $warehouse->id,
        'type' => 'entry',
        'quantity' => 100,
        'cost_price' => 300,
        'movement_date' => now()->toDateString(),
        'lot_number' => 'ETQ-01',
    ])->assertSessionHasErrors(['raw_material_id' => 'Esta materia prima no controla inventario: no se registran compras ni salidas de ella.']);

    expect(InventoryBatch::query()->where('raw_material_id', $this->label->id)->exists())->toBeFalse();

    $this->get(route('inventory-movements.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->reloadOnly('rawMaterials', fn (AssertableInertia $reload) => $reload
                ->where('rawMaterials', fn ($materials) => ($ids = collect($materials)->pluck('id'))
                    ->contains($this->container->id)
                    && ! $ids->contains($this->label->id))));
});

it('exige precio a una materia prima sin control de inventario, aunque sea 0, y no deja borrarlo', function () {
    $new = fn (array $overrides) => packagingMaterialPayload($this->label, ['code' => 'ETQ-NUEVA', 'tracks_inventory' => false, ...$overrides]);

    $this->post(route('raw-materials.store'), $new([]))
        ->assertSessionHasErrors(['current_price' => 'Escribe el precio de referencia: sin control de inventario es el que usan los costos. Puede ser 0.']);
    $this->post(route('raw-materials.store'), $new(['current_price' => '0']))
        ->assertSessionHasNoErrors();

    $this->put(route('raw-materials.update', $this->label), packagingMaterialPayload($this->label, ['current_price' => null]))
        ->assertSessionHasErrors('current_price');
    $this->put(route('raw-materials.update', $this->container), packagingMaterialPayload($this->container, ['tracks_inventory' => false]))
        ->assertSessionHasErrors('current_price');

    expect(RawMaterial::query()->where('code', 'ETQ-NUEVA')->sole()->current_price)->toBe('0.0000')
        ->and($this->label->fresh()->current_price)->toBe('300.0000')
        ->and($this->container->fresh()->tracks_inventory)->toBeTrue();
});

it('solo quien maneja costos crea materias primas sin control de inventario o se lo quita', function () {
    $role = Role::create(['name' => 'Bodega']);
    $role->givePermissionTo(['dashboard.view', 'raw_materials.view', 'raw_materials.create', 'raw_materials.edit']);
    $warehouseKeeper = User::factory()->create();
    $warehouseKeeper->assignRole($role);
    $message = 'Solo quien puede modificar los parámetros de costo cambia el control de inventario: sin él, el precio se escribe a mano y entra en los costos.';

    $this->actingAs($warehouseKeeper)
        ->post(route('raw-materials.store'), packagingMaterialPayload($this->label, ['code' => 'ETQ-BODEGA', 'tracks_inventory' => false]))
        ->assertSessionHasErrors(['tracks_inventory' => $message]);
    $this->actingAs($warehouseKeeper)
        ->put(route('raw-materials.update', $this->container), packagingMaterialPayload($this->container, ['tracks_inventory' => false]))
        ->assertSessionHasErrors(['tracks_inventory' => $message]);

    // Tampoco lo activa: dejaría sin saldo las OP que la usan.
    $this->actingAs($warehouseKeeper)
        ->put(route('raw-materials.update', $this->label), packagingMaterialPayload($this->label, ['tracks_inventory' => true]))
        ->assertSessionHasErrors(['tracks_inventory' => $message]);

    expect(RawMaterial::query()->where('code', 'ETQ-BODEGA')->exists())->toBeFalse()
        ->and($this->container->fresh()->tracks_inventory)->toBeTrue()
        ->and($this->label->fresh()->tracks_inventory)->toBeFalse();
});

it('al activar el control de inventario toma el precio de sus lotes sin esperar a la próxima compra', function () {
    // Un lote de cuando ya controlaba inventario, o cargado antes de apagarlo.
    InventoryBatch::factory()->create([
        'raw_material_id' => $this->label->id,
        'warehouse_id' => Warehouse::factory()->create()->id,
        'initial_quantity' => '100',
        'remaining_quantity' => '100',
        'unit_price' => '350',
    ]);

    $this->put(route('raw-materials.update', $this->label), packagingMaterialPayload($this->label, ['tracks_inventory' => true]))
        ->assertSessionHasNoErrors();

    expect($this->label->fresh()->current_price)->toBe('350.0000');
});

it('cambiar el CIF de un producto sin costo de granel no deja en 0 su precio ni el de sus presentaciones', function () {
    $this->product->update(['current_cost' => null, 'current_price' => '50000']);
    $this->variant->update(['current_cost' => '15000', 'current_price' => '16500']);

    $this->put(route('products.update', $this->product), [
        'code' => $this->product->code,
        'name' => $this->product->name,
        'brand' => $this->product->brand,
        'category_id' => $this->product->category_id,
        'unit_of_measure_id' => $this->product->unit_of_measure_id,
        'cif_percentage' => 20,
        'price_threshold' => 0,
    ])->assertSessionHasNoErrors();

    expect($this->product->fresh())
        ->cif_percentage->toBe('20.00')
        ->current_price->toBe('50000.0000')
        ->and($this->variant->fresh()->current_price)->toBe('16500.0000');
});

it('rechaza un stock mínimo que no cabe en la columna, en vez de un error del servidor', function () {
    $this->put(route('raw-materials.update', $this->container), packagingMaterialPayload($this->container, ['minimum_stock' => '123456789']))
        ->assertSessionHasErrors('minimum_stock');
});

it('vuelve a revisar el saldo al quitar el control de inventario, con la materia prima bloqueada', function () {
    // Una compra que entró después de validar el formulario: la Action no debe confiar solo en el request.
    InventoryBatch::factory()->create([
        'raw_material_id' => $this->container->id,
        'warehouse_id' => Warehouse::factory()->create()->id,
        'initial_quantity' => '10',
        'remaining_quantity' => '10',
    ]);

    expect(fn () => app(UpdateRawMaterialAction::class)->execute($this->container, ['tracks_inventory' => false, 'current_price' => '2000']))
        ->toThrow(ValidationException::class);

    expect($this->container->fresh()->tracks_inventory)->toBeTrue();
});

it('pide confirmación para activar el control de inventario si una OP abierta la usa', function () {
    $order = ProductionOrder::factory()->create(['status' => ProductionOrderStatus::InProgress]);
    ProductionOrderDetail::create(['production_order_id' => $order->id, 'raw_material_id' => $this->label->id, 'step_order' => 1, 'planned_quantity' => '1', 'unit_cost' => '0', 'total_cost' => '0']);

    $this->get(route('raw-materials.edit', $this->label))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('openProductionOrdersCount', 1));

    $this->put(route('raw-materials.update', $this->label), packagingMaterialPayload($this->label, ['tracks_inventory' => true]))
        ->assertSessionHasErrors(['confirm_tracking_change' => 'Confirma: 1 orden de producción abierta usa esta materia prima y, sin saldo, no se podrá completar hasta registrar compras.']);
    expect($this->label->fresh()->tracks_inventory)->toBeFalse();

    $this->put(route('raw-materials.update', $this->label), packagingMaterialPayload($this->label, ['tracks_inventory' => true, 'confirm_tracking_change' => true]))
        ->assertSessionHasNoErrors();
    expect($this->label->fresh()->tracks_inventory)->toBeTrue();
});

it('resuelve la alerta de stock bajo al quitar el control de inventario y la evalúa al activarlo', function () {
    // Sin saldo y con mínimo: al controlar inventario corresponde la alerta; sin control, ninguna.
    $this->container->update(['minimum_stock' => '10']);
    app(AlertService::class)->evaluateLowStock($this->container->id);
    $lowStock = fn () => Alert::query()->where('type', AlertType::StockBajo)->where('raw_material_id', $this->container->id)->whereNull('resolved_at');
    expect($lowStock()->exists())->toBeTrue();

    $this->put(route('raw-materials.update', $this->container), packagingMaterialPayload($this->container, ['minimum_stock' => 10, 'tracks_inventory' => false, 'current_price' => '2000']))
        ->assertSessionHasNoErrors();
    expect($lowStock()->exists())->toBeFalse();

    $this->put(route('raw-materials.update', $this->container), packagingMaterialPayload($this->container, ['minimum_stock' => 10, 'tracks_inventory' => true]))
        ->assertSessionHasNoErrors();
    expect($lowStock()->exists())->toBeTrue();
});

it('el servicio de movimientos también rechaza una materia prima sin control de inventario', function () {
    // El request ya la rechaza; esto cubre a quien llame al servicio sin pasar por él (y la revisa con la fila bloqueada).
    expect(fn () => app(InventoryService::class)->storeMovement([
        'raw_material_id' => $this->label->id,
        'warehouse_id' => Warehouse::factory()->create()->id,
        'type' => 'entry',
        'quantity' => '10',
        'cost_price' => '300',
        'movement_date' => now()->toDateString(),
        'lot_number' => 'ETQ-02',
    ], $this->admin->id))->toThrow(ValidationException::class);

    expect(InventoryBatch::query()->where('raw_material_id', $this->label->id)->exists())->toBeFalse();
});

it('cuenta como uso el envase de una presentación del plan de envasado de una OP abierta', function () {
    // Un envase sin control de inventario: la OP lo consume al completarse por la presentación de su plan, no por una
    // línea de fórmula ni un ajuste. Lo que cuenta es el uso, no el tipo de insumo.
    $this->container->update(['tracks_inventory' => false]);
    $order = ProductionOrder::factory()->create(['status' => ProductionOrderStatus::Pending, 'product_id' => $this->product->id]);
    ProductionOrderPackagingPlan::create(['production_order_id' => $order->id, 'product_variant_id' => $this->variant->id, 'planned_units' => '10']);
    ProductionOrder::factory()->create(['status' => ProductionOrderStatus::Completed, 'product_id' => $this->product->id])
        ->packagingPlans()->create(['product_variant_id' => $this->variant->id, 'planned_units' => '5']);

    expect(app(RawMaterialUsageService::class)->openProductionOrdersCount($this->container->fresh()))->toBe(1);

    $this->put(route('raw-materials.update', $this->container), packagingMaterialPayload($this->container, ['tracks_inventory' => true]))
        ->assertSessionHasErrors('confirm_tracking_change');
});

it('al cambiar el CIF de un producto sin fórmula recalcula su precio y el de sus presentaciones con la misma regla', function () {
    $this->put(route('products.update', $this->product), [
        'code' => $this->product->code,
        'name' => $this->product->name,
        'brand' => $this->product->brand,
        'category_id' => $this->product->category_id,
        'unit_of_measure_id' => $this->product->unit_of_measure_id,
        'cif_percentage' => 20,
        'price_threshold' => 0,
    ])->assertSessionHasNoErrors();

    // Producto: 10.000 × 1,20. Galón: (10.000 + 2.000 + 300) × 1,20.
    expect($this->product->fresh()->current_price)->toBe('12000.0000')
        ->and($this->variant->fresh())
        ->current_cost->toBe('12300.0000')
        ->current_price->toBe('14760.0000');
});

it('sin CIF no calcula precio, ni en el producto sin fórmula ni en sus presentaciones', function () {
    // El formulario exige el CIF, pero la columna lo admite vacío. Antes el controlador lo tomaba como 0 y fijaba el
    // precio del producto en su costo, mientras sus presentaciones seguían otra regla.
    $this->product->update(['cif_percentage' => null, 'current_price' => '11000']);
    $this->variant->update(['current_cost' => '12300', 'current_price' => '13530']);

    app(ProductionCostRecalculationService::class)->repriceProductWithoutFormula($this->product->fresh());

    expect($this->product->fresh()->current_price)->toBe('11000.0000')
        ->and($this->variant->fresh())
        ->current_cost->toBe('12300.0000')
        ->current_price->toBe('13530.0000');
});
