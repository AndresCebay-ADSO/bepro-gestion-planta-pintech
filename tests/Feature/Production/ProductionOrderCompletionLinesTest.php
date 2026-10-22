<?php

declare(strict_types=1);

use App\Enums\ProductionOrderStatus;
use App\Enums\SystemRole;
use App\Models\FinishedProductBatch;
use App\Models\Formula;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\ProductionOrderLineAdjustment;
use App\Models\ProductionOrderPackagingPlan;
use App\Models\ProductionRemnant;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\RemnantConsumption;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Production\ProductionOrderCompletionLines;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

// Auditoría 3.6, PR 6: completar (y enviar a revisión) no confía en la lista que trae la petición. Con la OP bloqueada,
// los ingredientes y las presentaciones son exactamente los de la OP, los ajustes y los saldos son los que vio la
// pantalla, y algo se tiene que haber envasado (OPL-01, OPL-02, D-OPL1, D-OPL3, P6-1, P6-2).

beforeEach(function () {
    Queue::fake();
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create(['job_title' => 'Jefe de producción', 'signature_path' => 'signatures/test.png']);
    $this->admin->assignRole(SystemRole::Admin->value);
    $this->actingAs($this->admin);

    $unit = UnitOfMeasure::factory()->create();
    $this->warehouse = Warehouse::factory()->factory()->create();
    $this->product = Product::factory()->create([
        'category_id' => ProductCategory::factory()->create()->id,
        'unit_of_measure_id' => $unit->id,
    ]);
    $formula = Formula::factory()->create(['product_id' => $this->product->id, 'created_by' => $this->admin->id]);

    $chemical = function (string $code) use ($unit): RawMaterial {
        $rawMaterial = RawMaterial::factory()->create(['code' => $code, 'unit_of_measure_id' => $unit->id]);
        InventoryBatch::factory()->create([
            'raw_material_id' => $rawMaterial->id,
            'warehouse_id' => $this->warehouse->id,
            'initial_quantity' => '100',
            'remaining_quantity' => '100',
            'unit_price' => '5',
        ]);

        return $rawMaterial;
    };
    $this->resin = $chemical('RESINA');
    $this->solvent = $chemical('SOLVENTE');

    $this->order = ProductionOrder::factory()->inProgress()->create([
        'product_id' => $this->product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 15,
    ]);
    $this->resinLine = ProductionOrderDetail::create([
        'production_order_id' => $this->order->id, 'raw_material_id' => $this->resin->id,
        'planned_quantity' => '10', 'unit_cost' => '5', 'total_cost' => '50',
    ]);
    $this->solventLine = ProductionOrderDetail::create([
        'production_order_id' => $this->order->id, 'raw_material_id' => $this->solvent->id,
        'planned_quantity' => '5', 'unit_cost' => '5', 'total_cost' => '25',
    ]);

    $variant = fn (string $value) => ProductVariant::factory()->create([
        'product_id' => $this->product->id,
        'presentation_value' => $value,
        'package_raw_material_id' => null,
        'label_raw_material_id' => null,
    ]);
    $this->gallon = $variant('1');
    $this->bucket = $variant('5');
    $this->gallonPlan = ProductionOrderPackagingPlan::createForVariant((int) $this->order->id, (int) $this->gallon->id, '5');
    $this->bucketPlan = ProductionOrderPackagingPlan::createForVariant((int) $this->order->id, (int) $this->bucket->id, '2');
});

/**
 * Lo que envía la pantalla al confirmar: todas las líneas que muestra. 5 galones + 2 cuñetes de 5 = 15 galones.
 *
 * @param  array<string, mixed>  $override
 * @return array<string, mixed>
 */
function completionLinesBody(object $test, array $override = []): array
{
    return array_replace([
        'ingredients' => [
            ['id' => $test->resinLine->id, 'actual_quantity' => '10'],
            ['id' => $test->solventLine->id, 'actual_quantity' => '5'],
        ],
        'packaging' => [
            ['id' => $test->gallonPlan->id, 'actual_units' => '5'],
            ['id' => $test->bucketPlan->id, 'actual_units' => '2'],
        ],
        'line_adjustment_ids' => [],
        'remnant_consumption_ids' => [],
        'actual_yield_quantity' => '15',
        'density_kg_per_gallon' => '5',
        'quality_responsible_user_id' => $test->admin->id,
        'responsible_name' => 'Operario',
    ], $override);
}

function expectOrderUntouched(ProductionOrder $order, ProductionOrderStatus $status = ProductionOrderStatus::InProgress): void
{
    expect($order->fresh()->status)->toBe($status)
        ->and(InventoryMovement::query()->where('production_order_id', $order->id)->count())->toBe(0)
        ->and(FinishedProductBatch::query()->where('production_order_id', $order->id)->count())->toBe(0);
}

it('completa la orden cuando la petición trae todas sus líneas', function () {
    $this->post(route('production-orders.complete', $this->order), completionLinesBody($this))
        ->assertSessionHasNoErrors();

    expect($this->order->fresh()->status)->toBe(ProductionOrderStatus::Completed)
        ->and(FinishedProductBatch::query()->where('production_order_id', $this->order->id)->count())->toBe(2);
});

it('no completa la orden si falta un ingrediente (OPL-01)', function () {
    $this->post(route('production-orders.complete', $this->order), completionLinesBody($this, [
        'ingredients' => [['id' => $this->resinLine->id, 'actual_quantity' => '10']],
    ]))->assertSessionHasErrors('ingredients');

    expectOrderUntouched($this->order);
});

it('un ingrediente no usado se registra con 0 y no sale del inventario', function () {
    $this->post(route('production-orders.complete', $this->order), completionLinesBody($this, [
        'ingredients' => [
            ['id' => $this->resinLine->id, 'actual_quantity' => '10'],
            ['id' => $this->solventLine->id, 'actual_quantity' => '0'],
        ],
    ]))->assertSessionHasNoErrors();

    expect($this->order->fresh()->status)->toBe(ProductionOrderStatus::Completed)
        ->and(InventoryMovement::query()->where('production_order_id', $this->order->id)->pluck('raw_material_id')->all())
        ->toBe([$this->resin->id])
        ->and((string) $this->solventLine->fresh()->actual_quantity)->toBe('0.0000');
});

it('no completa la orden si falta una presentación (OPL-02)', function () {
    $this->post(route('production-orders.complete', $this->order), completionLinesBody($this, [
        'packaging' => [['id' => $this->gallonPlan->id, 'actual_units' => '5']],
        'actual_yield_quantity' => '5',
    ]))->assertSessionHasErrors(['packaging' => __(ProductionOrderCompletionLines::ORDER_CHANGED_MESSAGE)]);

    expectOrderUntouched($this->order);
    expect($this->bucketPlan->fresh()->actual_units)->toBeNull();
});

it('rechaza la lista vieja: una presentación agregada después de abrir la página (OPL-02)', function () {
    $body = completionLinesBody($this);
    $drum = ProductVariant::factory()->create([
        'product_id' => $this->product->id, 'presentation_value' => '55',
        'package_raw_material_id' => null, 'label_raw_material_id' => null,
    ]);
    ProductionOrderPackagingPlan::createForVariant((int) $this->order->id, (int) $drum->id, '1');

    $this->post(route('production-orders.complete', $this->order), $body)
        ->assertSessionHasErrors(['packaging' => __(ProductionOrderCompletionLines::ORDER_CHANGED_MESSAGE)]);

    expectOrderUntouched($this->order);
});

it('una presentación borrada después de abrir la página da el mismo aviso, no «no es válido»', function () {
    $body = completionLinesBody($this);
    $this->bucketPlan->delete();

    $this->post(route('production-orders.complete', $this->order), $body)
        ->assertSessionHasErrors(['packaging.1.id' => __(ProductionOrderCompletionLines::ORDER_CHANGED_MESSAGE)]);

    expectOrderUntouched($this->order);
});

it('no completa la orden si no se envasó nada ni quedó saldo (D-OPL1)', function () {
    $this->post(route('production-orders.complete', $this->order), completionLinesBody($this, [
        'packaging' => [
            ['id' => $this->gallonPlan->id, 'actual_units' => '0'],
            ['id' => $this->bucketPlan->id, 'actual_units' => '0'],
        ],
    ]))->assertSessionHasErrors(['packaging' => __(ProductionOrderCompletionLines::NOTHING_PACKED_MESSAGE)]);

    expectOrderUntouched($this->order);
});

it('una orden sin presentaciones tampoco se completa sin saldo (D-OPL1)', function () {
    ProductionOrderPackagingPlan::query()->where('production_order_id', $this->order->id)->delete();

    $this->post(route('production-orders.complete', $this->order), completionLinesBody($this, [
        'packaging' => [],
    ]))->assertSessionHasErrors(['packaging' => __(ProductionOrderCompletionLines::NOTHING_PACKED_MESSAGE)]);

    expectOrderUntouched($this->order);
});

it('se completa sin envasar si todo quedó como saldo', function () {
    $this->post(route('production-orders.complete', $this->order), completionLinesBody($this, [
        'packaging' => [
            ['id' => $this->gallonPlan->id, 'actual_units' => '0'],
            ['id' => $this->bucketPlan->id, 'actual_units' => '0'],
        ],
        'remnant_quantity_gallons' => '15',
    ]))->assertSessionHasNoErrors();

    expect($this->order->fresh()->status)->toBe(ProductionOrderStatus::Completed)
        ->and(FinishedProductBatch::query()->where('production_order_id', $this->order->id)->count())->toBe(0)
        ->and(ProductionRemnant::query()->where('source_order_id', $this->order->id)->value('original_quantity_gallons'))
        ->toBe('15.0000');
});

it('rechaza un ajuste de línea que la pantalla no mostraba (P6-2)', function () {
    $body = completionLinesBody($this);
    ProductionOrderLineAdjustment::create([
        'production_order_id' => $this->order->id, 'raw_material_id' => $this->resin->id,
        'quantity' => '2', 'reason' => 'Corrección de viscosidad', 'created_by' => $this->admin->id,
    ]);

    $this->post(route('production-orders.complete', $this->order), $body)
        ->assertSessionHasErrors(['line_adjustment_ids' => __(ProductionOrderCompletionLines::ORDER_CHANGED_MESSAGE)]);

    expectOrderUntouched($this->order);
});

it('consume el ajuste de línea que la pantalla mostraba', function () {
    $adjustment = ProductionOrderLineAdjustment::create([
        'production_order_id' => $this->order->id, 'raw_material_id' => $this->resin->id,
        'quantity' => '2', 'reason' => 'Corrección de viscosidad', 'created_by' => $this->admin->id,
    ]);

    $this->post(route('production-orders.complete', $this->order), completionLinesBody($this, [
        'line_adjustment_ids' => [$adjustment->id],
    ]))->assertSessionHasNoErrors();

    expect(InventoryMovement::query()->where('production_order_id', $this->order->id)->where('raw_material_id', $this->resin->id)->sum('quantity'))
        ->toEqual(12);
});

it('rechaza un ajuste que se borró después de abrir la página (P6-2)', function () {
    $adjustment = ProductionOrderLineAdjustment::create([
        'production_order_id' => $this->order->id, 'raw_material_id' => $this->resin->id,
        'quantity' => '2', 'reason' => 'Corrección de viscosidad', 'created_by' => $this->admin->id,
    ]);
    $body = completionLinesBody($this, ['line_adjustment_ids' => [$adjustment->id]]);
    $adjustment->delete();

    $this->post(route('production-orders.complete', $this->order), $body)
        ->assertSessionHasErrors(['line_adjustment_ids.0' => __(ProductionOrderCompletionLines::ORDER_CHANGED_MESSAGE)]);

    expectOrderUntouched($this->order);
});

it('rechaza un saldo consumido que la pantalla no mostraba (P6-2)', function () {
    $body = completionLinesBody($this);
    $source = ProductionOrder::factory()->completed()->create([
        'product_id' => $this->product->id, 'formula_id' => $this->order->formula_id, 'warehouse_id' => $this->warehouse->id,
    ]);
    $remnant = ProductionRemnant::factory()->create([
        'source_order_id' => $source->id, 'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
        'created_by' => $this->admin->id,
    ]);
    RemnantConsumption::create([
        'remnant_id' => $remnant->id, 'target_order_id' => $this->order->id, 'quantity_gallons' => '1',
        'quantity_kg' => '5', 'consumed_by' => $this->admin->id, 'consumed_at' => now(),
    ]);

    $this->post(route('production-orders.complete', $this->order), $body)
        ->assertSessionHasErrors(['remnant_consumption_ids' => __(ProductionOrderCompletionLines::ORDER_CHANGED_MESSAGE)]);

    expectOrderUntouched($this->order);
});

it('exige las listas de ajustes y saldos que vio la pantalla', function (string $field) {
    $body = completionLinesBody($this);
    unset($body[$field]);

    $this->post(route('production-orders.complete', $this->order), $body)->assertSessionHasErrors($field);

    expectOrderUntouched($this->order);
})->with(['line_adjustment_ids', 'remnant_consumption_ids']);

it('al enviar a revisión también exige todas las presentaciones (P6-1)', function () {
    $this->post(route('production-orders.submit-for-review', $this->order), completionLinesBody($this, [
        'packaging' => [['id' => $this->gallonPlan->id, 'actual_units' => '5']],
    ]))->assertSessionHasErrors(['packaging' => __(ProductionOrderCompletionLines::ORDER_CHANGED_MESSAGE)]);

    expect($this->order->fresh()->status)->toBe(ProductionOrderStatus::InProgress)
        ->and($this->gallonPlan->fresh()->actual_units)->toBeNull();
});

it('al enviar a revisión también exige todos los ingredientes (P6-1)', function () {
    $this->post(route('production-orders.submit-for-review', $this->order), completionLinesBody($this, [
        'ingredients' => [['id' => $this->resinLine->id, 'actual_quantity' => '10']],
    ]))->assertSessionHasErrors('ingredients');

    expect($this->order->fresh()->status)->toBe(ProductionOrderStatus::InProgress);
});

it('no envía a revisión si no se envasó nada ni quedó saldo (P6-1)', function () {
    $this->post(route('production-orders.submit-for-review', $this->order), completionLinesBody($this, [
        'packaging' => [
            ['id' => $this->gallonPlan->id, 'actual_units' => '0'],
            ['id' => $this->bucketPlan->id, 'actual_units' => '0'],
        ],
    ]))->assertSessionHasErrors(['packaging' => __(ProductionOrderCompletionLines::NOTHING_PACKED_MESSAGE)]);

    expect($this->order->fresh()->status)->toBe(ProductionOrderStatus::InProgress);
});

it('envía a revisión con todas las líneas', function () {
    $this->post(route('production-orders.submit-for-review', $this->order), completionLinesBody($this))
        ->assertSessionHasNoErrors();

    expect($this->order->fresh()->status)->toBe(ProductionOrderStatus::PendingReview);
});

it('el saldo que informa la planta al enviar a revisión llega a quien aprueba, y la orden se puede cerrar', function () {
    $body = completionLinesBody($this, [
        'packaging' => [
            ['id' => $this->gallonPlan->id, 'actual_units' => '0'],
            ['id' => $this->bucketPlan->id, 'actual_units' => '0'],
        ],
        'remnant_quantity_gallons' => '15',
        'remnant_notes' => 'Tanque 2, tapa azul',
    ]);

    $this->post(route('production-orders.submit-for-review', $this->order), $body)->assertSessionHasNoErrors();

    $order = $this->order->fresh();
    expect($order->status)->toBe(ProductionOrderStatus::PendingReview)
        ->and($order->remnant_quantity_gallons)->toBe('15.0000')
        ->and($order->remnant_notes)->toBe('Tanque 2, tapa azul');

    // La pantalla de quien revisa trae el saldo: antes llegaba vacío y aprobar daba «algo se tiene que envasar».
    $this->get(route('production-orders.show', $this->order))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('order.remnant_quantity_gallons', '15.0000')
        ->where('order.remnant_notes', 'Tanque 2, tapa azul'));

    $this->post(route('production-orders.complete', $this->order), $body)->assertSessionHasNoErrors();

    expect($this->order->fresh()->status)->toBe(ProductionOrderStatus::Completed)
        ->and(ProductionRemnant::query()->where('source_order_id', $this->order->id)->value('notes'))->toBe('Tanque 2, tapa azul');
});

// Auditoría 3.6, PR 10 (OPL-07): las horas de agitación y envasado se aceptaban con el fin antes del inicio y con el año
// 9999. Se comparan como instantes: una fase que cruza la medianoche es válida.

it('rechaza un fin de agitación anterior al inicio, al completar y al enviar a revisión (OPL-07)', function (string $route) {
    $this->post(route($route, $this->order), completionLinesBody($this, [
        'agitation_start_time' => '2026-10-13T15:00:00Z',
        'agitation_end_time' => '2026-10-13T13:00:00Z',
    ]))->assertSessionHasErrors(['agitation_end_time' => 'La hora de fin de agitación no puede ser anterior a la de inicio.']);

    expect($this->order->fresh()->status)->toBe(ProductionOrderStatus::InProgress);
})->with(['production-orders.complete', 'production-orders.submit-for-review']);

it('rechaza un envasado con el fin antes del inicio', function () {
    $this->post(route('production-orders.complete', $this->order), completionLinesBody($this, [
        'packaging_start_time' => '2026-10-13T15:00:00Z',
        'packaging_end_time' => '2026-10-13T14:59:00Z',
    ]))->assertSessionHasErrors('packaging_end_time');
});

it('rechaza una hora en el futuro, como el año 9999 (OPL-07)', function (string $field) {
    $this->post(route('production-orders.complete', $this->order), completionLinesBody($this, [
        $field => '9999-12-31T08:00:00Z',
    ]))->assertSessionHasErrors($field);

    expectOrderUntouched($this->order);
})->with(['agitation_start_time', 'agitation_end_time', 'packaging_start_time', 'packaging_end_time']);

it('rechaza una fase de más de 48 horas', function () {
    $this->post(route('production-orders.complete', $this->order), completionLinesBody($this, [
        'agitation_start_time' => '2026-10-01T08:00:00Z',
        'agitation_end_time' => '2026-10-03T08:01:00Z',
    ]))->assertSessionHasErrors(['agitation_end_time' => 'La agitación no puede durar más de 48 horas.']);
});

it('rechaza un texto que no es una fecha y hora completas', function (string $value) {
    $this->post(route('production-orders.complete', $this->order), completionLinesBody($this, [
        'agitation_start_time' => $value,
    ]))->assertSessionHasErrors(['agitation_start_time' => 'Elige una fecha y hora válidas.']);
})->with(['mañana', '2026-02-30T08:00', '2026-10-13', '13/10/2026 08:00', '2026-10-13T25:00']);

it('acepta una fase que cruza la medianoche y horas sin zona como hora de planta', function () {
    $this->post(route('production-orders.complete', $this->order), completionLinesBody($this, [
        // 22:00 a 02:00 en Bogotá (UTC-5) = 03:00 a 07:00 UTC del día siguiente.
        'agitation_start_time' => '2026-10-13T22:00',
        'agitation_end_time' => '2026-10-14T02:00',
        'packaging_start_time' => '2026-10-14T08:00:00.000Z',
        'packaging_end_time' => '2026-10-14T10:30:00-05:00',
    ]))->assertSessionHasNoErrors();

    $order = $this->order->fresh();

    expect($order->status)->toBe(ProductionOrderStatus::Completed)
        ->and($order->agitation_start_time->utc()->format('Y-m-d H:i'))->toBe('2026-10-14 03:00')
        ->and($order->agitation_end_time->utc()->format('Y-m-d H:i'))->toBe('2026-10-14 07:00')
        ->and($order->packaging_end_time->utc()->format('Y-m-d H:i'))->toBe('2026-10-14 15:30');
});

it('las horas son opcionales', function () {
    $this->post(route('production-orders.complete', $this->order), completionLinesBody($this, [
        'agitation_start_time' => '',
        'agitation_end_time' => null,
    ]))->assertSessionHasNoErrors();
});
