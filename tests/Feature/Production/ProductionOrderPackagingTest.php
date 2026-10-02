<?php

declare(strict_types=1);

use App\Actions\Production\BuildProductionOrderExportDataAction;
use App\Enums\ProductionOrderStatus;
use App\Enums\RawMaterialType;
use App\Models\FinishedInventoryMovement;
use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\ProductionOrderPackagingPlan;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia;

// 3.7, PR B: la OP descuenta solo los envases nuevos (los reutilizados cuestan 0), consume la etiqueta del plan y suma
// los dos al costo del lote (decisiones del 2026-09-29 y 2026-09-30).

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create(['job_title' => 'Jefe de calidad', 'signature_path' => 'signatures/test.png']);
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    $this->warehouse = Warehouse::factory()->factory()->create();
    $unit = UnitOfMeasure::factory()->create();

    // Granel: un químico a $5 por unidad.
    $this->chemical = RawMaterial::factory()->create(['code' => 'RESINA', 'unit_of_measure_id' => $unit->id]);
    $this->chemicalBatch = InventoryBatch::factory()->create([
        'raw_material_id' => $this->chemical->id,
        'warehouse_id' => $this->warehouse->id,
        'initial_quantity' => '100',
        'remaining_quantity' => '100',
        'unit_price' => '5',
    ]);

    // Envase con inventario: 20 a $1.000. Etiqueta sin inventario: $300 escrito a mano.
    $this->container = RawMaterial::factory()->create([
        'code' => 'GALON-MET',
        'category_id' => RawMaterialCategory::factory()->container()->create()->id,
    ]);
    $this->containerBatch = InventoryBatch::factory()->create([
        'raw_material_id' => $this->container->id,
        'warehouse_id' => $this->warehouse->id,
        'initial_quantity' => '20',
        'remaining_quantity' => '20',
        'unit_price' => '1000',
    ]);
    $this->labelCategory = RawMaterialCategory::factory()->label()->create();
    $this->label = RawMaterial::factory()->withoutInventoryTracking()->create([
        'code' => 'ETQ-GALON',
        'category_id' => $this->labelCategory->id,
        'current_price' => '300',
    ]);

    $this->product = Product::factory()->create([
        'category_id' => ProductCategory::factory()->create()->id,
        'unit_of_measure_id' => $unit->id,
    ]);
    $formula = Formula::factory()->create(['product_id' => $this->product->id, 'created_by' => $this->admin->id]);
    FormulaDetail::create(['formula_id' => $formula->id, 'raw_material_id' => $this->chemical->id, 'quantity' => 0.5, 'unit_of_measure_id' => $unit->id]);

    $this->variant = ProductVariant::factory()->create([
        'product_id' => $this->product->id,
        'presentation_value' => 1,
        'presentation_label' => 'Galón',
        'package_raw_material_id' => $this->container->id,
        'label_raw_material_id' => $this->label->id,
    ]);

    $this->order = ProductionOrder::factory()->create([
        'product_id' => $this->product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 10,
        'status' => ProductionOrderStatus::InProgress,
    ]);
    $this->detail = ProductionOrderDetail::create([
        'production_order_id' => $this->order->id,
        'raw_material_id' => $this->chemical->id,
        'step_order' => 1,
        'planned_quantity' => '52',
        'unit_cost' => '5',
        'total_cost' => '260',
    ]);
});

/**
 * Agrega el plan como lo hace la pantalla: copia la etiqueta habitual de la presentación.
 */
function packagingPlanFor(ProductionOrder $order, ProductVariant $variant, int $units = 10): ProductionOrderPackagingPlan
{
    test()->post(route('production-orders.packaging-plans.store', $order), [
        'product_variant_id' => $variant->id,
        'planned_units' => $units,
    ])->assertSessionHasNoErrors();

    return ProductionOrderPackagingPlan::query()->where('production_order_id', $order->id)->latest('id')->firstOrFail();
}

/**
 * @param  array<string, mixed>  $packaging
 * @return array<string, mixed>
 */
function completePackagingPayload(ProductionOrderDetail $detail, array $packaging, User $signer): array
{
    return [
        'actual_yield_quantity' => $packaging['actual_units'],
        'responsible_name' => 'Operario',
        'density_kg_per_gallon' => 5,
        'quality_responsible_user_id' => $signer->id,
        'ingredients' => [['id' => $detail->id, 'actual_quantity' => 52]],
        'packaging' => [$packaging],
    ];
}

it('copia al plan la etiqueta habitual de la presentación', function () {
    $plan = packagingPlanFor($this->order, $this->variant);

    expect($plan->label_raw_material_id)->toBe($this->label->id);
});

it('descuenta solo los envases nuevos, consume las etiquetas usadas y suma los dos al costo del lote', function () {
    $plan = packagingPlanFor($this->order, $this->variant);

    // 10 galones: 7 envases nuevos (3 reutilizados) y 12 etiquetas (2 dañadas al pegar).
    $this->post(route('production-orders.complete', $this->order), completePackagingPayload($this->detail, [
        'id' => $plan->id,
        'actual_units' => 10,
        'new_containers_used' => 7,
        'label_raw_material_id' => $this->label->id,
        'labels_used' => 12,
    ], $this->admin))->assertSessionHasNoErrors();

    expect($this->order->fresh()->status)->toBe(ProductionOrderStatus::Completed)
        ->and($this->containerBatch->fresh()->remaining_quantity)->toBe('13.0000')
        ->and(InventoryMovement::query()->where('raw_material_id', $this->label->id)->sole())
        ->quantity->toBe('12.0000')
        ->batch_id->toBeNull()
        ->and($plan->fresh())
        ->new_containers_used->toBe('7.0000')
        ->labels_used->toBe('12.0000');

    // Granel 52 × 5 = 260 entre 10 = 26. Empaque (7 × 1.000 + 12 × 300) / 10 = 1.060.
    expect(FinishedInventoryMovement::query()->where('production_order_id', $this->order->id)->sole()->cost_price)
        ->toBe('1086.0000');
});

it('sin cantidades usa una por unidad envasada, y sin etiqueta no consume ninguna', function () {
    $plan = packagingPlanFor($this->order, $this->variant);

    $this->post(route('production-orders.complete', $this->order), completePackagingPayload($this->detail, [
        'id' => $plan->id,
        'actual_units' => 10,
        'new_containers_used' => null,
        'label_raw_material_id' => null,
        'labels_used' => null,
    ], $this->admin))->assertSessionHasNoErrors();

    expect($this->containerBatch->fresh()->remaining_quantity)->toBe('10.0000')
        ->and(InventoryMovement::query()->where('raw_material_id', $this->label->id)->exists())->toBeFalse()
        ->and($plan->fresh())
        ->new_containers_used->toBe('10.0000')
        ->labels_used->toBeNull()
        // Granel 26 + 10 envases de 1.000 entre 10.
        ->and(FinishedInventoryMovement::query()->where('production_order_id', $this->order->id)->sole()->cost_price)
        ->toBe('1026.0000');
});

it('la vista previa calcula el costo con los envases nuevos y las etiquetas usadas', function () {
    $plan = packagingPlanFor($this->order, $this->variant);

    $this->postJson(route('production-orders.preview-costs', $this->order), [
        'ingredients' => [['id' => $this->detail->id, 'actual_quantity' => 52]],
        'packaging' => [[
            'id' => $plan->id,
            'actual_units' => 10,
            'new_containers_used' => '7',
            'label_raw_material_id' => $this->label->id,
            'labels_used' => '12',
        ]],
    ])->assertOk()->assertJsonPath('packaging.0.cost_price', '1086.0000');
});

it('guarda el empaque del plan al guardar el avance y lo muestra en la orden', function () {
    $plan = packagingPlanFor($this->order, $this->variant);

    $this->post(route('production-orders.submit-for-review', $this->order), [
        ...completePackagingPayload($this->detail, [
            'id' => $plan->id,
            'actual_units' => 10,
            'new_containers_used' => 4,
            'label_raw_material_id' => null,
            'labels_used' => null,
        ], $this->admin),
    ])->assertSessionHasNoErrors();

    expect($plan->fresh())
        ->new_containers_used->toBe('4.0000')
        ->label_raw_material_id->toBeNull();

    $this->get(route('production-orders.show', $this->order))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('order.packaging_plans.0.package_code', 'GALON-MET')
            ->where('order.packaging_plans.0.new_containers_used', '4.0000')
            ->where('labelMaterials', fn ($labels) => collect($labels)->pluck('id')->contains($this->label->id)));
});

it('rechaza como etiqueta del plan una materia prima que no es etiqueta, o una inactiva que el plan no tenía', function () {
    $plan = packagingPlanFor($this->order, $this->variant);
    $otherLabel = RawMaterial::factory()->inactive()->create(['category_id' => $this->labelCategory->id]);
    $payload = fn (int $labelId) => completePackagingPayload($this->detail, [
        'id' => $plan->id,
        'actual_units' => 10,
        'label_raw_material_id' => $labelId,
    ], $this->admin);

    $this->post(route('production-orders.complete', $this->order), $payload($this->container->id))
        ->assertSessionHasErrors('packaging.0.label_raw_material_id');
    $this->post(route('production-orders.complete', $this->order), $payload($otherLabel->id))
        ->assertSessionHasErrors(['packaging.0.label_raw_material_id' => 'La etiqueta elegida está inactiva.']);

    // La que el plan ya tiene se conserva aunque se haya desactivado.
    $this->label->update(['is_active' => false]);
    $this->post(route('production-orders.complete', $this->order), $payload($this->label->id))
        ->assertSessionHasNoErrors();
});

it('los ajustes de línea solo aceptan químicos y solo los ofrece', function () {
    $this->post(route('production-orders.line-adjustments.store', $this->order), [
        'raw_material_id' => $this->container->id,
        'quantity' => 2,
        'reason' => 'Faltó',
    ])->assertSessionHasErrors('raw_material_id');

    $this->get(route('production-orders.show', $this->order))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rawMaterials', fn ($materials) => ($ids = collect($materials)->pluck('id'))
                ->contains($this->chemical->id)
                && ! $ids->contains($this->container->id)
                && ! $ids->contains($this->label->id)));
});

it('cuenta la etiqueta del plan de una OP abierta como uso, para el control de inventario y el cambio de tipo', function () {
    // La del plan, no la habitual de la presentación: en la OP se puede cambiar.
    $otherLabel = RawMaterial::factory()->withoutInventoryTracking()->create(['category_id' => $this->labelCategory->id, 'current_price' => '100']);
    $plan = packagingPlanFor($this->order, $this->variant);
    $plan->update(['label_raw_material_id' => $otherLabel->id]);

    expect($otherLabel->openProductionOrdersCount())->toBe(1);

    $chemicals = RawMaterialCategory::factory()->create(['type' => RawMaterialType::Chemical]);
    $this->put(route('raw-materials.update', $otherLabel), [
        'code' => $otherLabel->code,
        'category_id' => $chemicals->id,
        'unit_of_measure_id' => $otherLabel->unit_of_measure_id,
        'minimum_stock' => 0,
        'alert_days_before_expiry' => 0,
    ])->assertSessionHasErrors(['category_id' => 'No se puede pasar a una categoría de tipo químico: esta materia prima está en uso en 1 orden de producción abierta.']);
});

it('el PDF y el Excel de la OP muestran el envase y la etiqueta de cada presentación', function () {
    packagingPlanFor($this->order, $this->variant);
    $order = app(BuildProductionOrderExportDataAction::class)->execute($this->order->fresh(), includeCosts: false);

    $pdf = view('pdf.production-order', ['order' => $order, 'logoBase64' => null, 'generatedAt' => 'hoy'])->render();
    $excel = view('excel.production-order', ['order' => $order])->render();

    expect($pdf)->toContain('Galón (GALON-MET · ETQ-GALON)')
        ->and($excel)->toContain('Galón (GALON-MET · ETQ-GALON)');
});

it('el PDF y el Excel no imprimen nada de empaque en una presentación sin envase ni etiqueta', function () {
    $this->variant->update(['package_raw_material_id' => null, 'label_raw_material_id' => null]);
    packagingPlanFor($this->order, $this->variant);
    $order = app(BuildProductionOrderExportDataAction::class)->execute($this->order->fresh(), includeCosts: false);

    $pdf = view('pdf.production-order', ['order' => $order, 'logoBase64' => null, 'generatedAt' => 'hoy'])->render();
    $excel = view('excel.production-order', ['order' => $order])->render();

    expect($order['packaging_plans'][0]['packaging_materials'])->toBeNull()
        ->and($pdf)->not->toContain('([])')->not->toContain('Galón (')
        ->and($excel)->not->toContain('([])')->not->toContain('Galón (');
});

it('una OP completada sigue mostrando el envase que consumió aunque cambie el de la presentación', function () {
    $plan = packagingPlanFor($this->order, $this->variant);
    $this->post(route('production-orders.complete', $this->order), completePackagingPayload($this->detail, [
        'id' => $plan->id,
        'actual_units' => 10,
    ], $this->admin))->assertSessionHasNoErrors();

    $otherContainer = RawMaterial::factory()->create(['code' => 'CUNETE', 'category_id' => $this->container->category_id]);
    $this->variant->update(['package_raw_material_id' => $otherContainer->id]);

    expect($plan->fresh()->package_raw_material_id)->toBe($this->container->id);

    $this->get(route('production-orders.show', $this->order))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('order.packaging_plans.0.package_code', 'GALON-MET'));
});

it('no acepta envases ni etiquetas en una presentación sin unidades envasadas', function () {
    $plan = packagingPlanFor($this->order, $this->variant);
    $other = ProductVariant::factory()->create(['product_id' => $this->product->id, 'presentation_value' => 1, 'package_raw_material_id' => null]);
    $otherPlan = packagingPlanFor($this->order, $other);

    $this->post(route('production-orders.complete', $this->order), [
        ...completePackagingPayload($this->detail, ['id' => $plan->id, 'actual_units' => 10], $this->admin),
        'packaging' => [
            ['id' => $plan->id, 'actual_units' => 10],
            ['id' => $otherPlan->id, 'actual_units' => 0, 'new_containers_used' => 3, 'labels_used' => 2],
        ],
    ])->assertSessionHasErrors([
        'packaging.1.new_containers_used' => 'Sin unidades envasadas no hay lote al que cargarlos: regístralos como salida manual de inventario con una nota.',
        'packaging.1.labels_used',
    ]);

    expect($this->order->fresh()->status)->toBe(ProductionOrderStatus::InProgress);
});

it('la vista previa acepta unidades con muchos decimales sin pasar por floats', function () {
    $plan = packagingPlanFor($this->order, $this->variant);

    // (string) 0.00001 sería «1.0E-5», que bcmath rechaza.
    $this->postJson(route('production-orders.preview-costs', $this->order), [
        'ingredients' => [['id' => $this->detail->id, 'actual_quantity' => 52]],
        'packaging' => [['id' => $plan->id, 'actual_units' => 0.00001]],
    ])->assertOk();
});

it('sin etiqueta no guarda etiquetas usadas, aunque lleguen en la petición', function () {
    // El operario escribió 12 y después eligió «Sin etiqueta»: el campo queda deshabilitado, pero el valor viajaba.
    $plan = packagingPlanFor($this->order, $this->variant);

    $this->post(route('production-orders.submit-for-review', $this->order), completePackagingPayload($this->detail, [
        'id' => $plan->id,
        'actual_units' => 10,
        'label_raw_material_id' => null,
        'labels_used' => 12,
    ], $this->admin))->assertSessionHasNoErrors();

    expect($plan->fresh())
        ->label_raw_material_id->toBeNull()
        ->labels_used->toBeNull();
});
