<?php

declare(strict_types=1);

use App\Enums\ProductionOrderStatus;
use App\Enums\SystemRole;
use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;

beforeEach(function () {
    $this->user = actingAsRole(SystemRole::Admin, [
        'job_title' => 'Analista de Calidad',
        'signature_path' => 'signatures/test.png',
    ]);

    $this->factory = Warehouse::create(['name' => 'Planta Cali', 'city' => 'Cali', 'type' => 'factory', 'is_active' => true]);
    $this->gallon = UnitOfMeasure::create(['code' => 'GAL', 'name' => 'Galón', 'symbol' => 'gal', 'to_liter_conversion' => 3.785]);
    $this->kilogram = UnitOfMeasure::create(['code' => 'KG', 'name' => 'Kilogramo', 'symbol' => 'kg', 'to_kg_conversion' => 1]);
    $this->gram = UnitOfMeasure::create(['code' => 'G', 'name' => 'Gramo', 'symbol' => 'g', 'to_kg_conversion' => 0.001]);

    $this->product = Product::create([
        'name' => 'Pintura Faltantes',
        'category_id' => ProductCategory::create(['name' => 'General'])->id,
        'unit_of_measure_id' => $this->gallon->id,
        'current_cost' => 10,
        'cif_percentage' => 0,
        'current_price' => 10,
        'price_threshold' => 0,
    ]);

    $this->formula = Formula::create([
        'product_id' => $this->product->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);
});

function shortageMaterial(string $code, array $attributes = []): RawMaterial
{
    return RawMaterial::create([
        'code' => $code,
        'unit_of_measure_id' => test()->kilogram->id,
        'current_price' => 1000,
        ...$attributes,
    ]);
}

function shortageFormulaLine(RawMaterial $material, string|int|float $quantity, ?UnitOfMeasure $unit = null): void
{
    static $step = 0;

    FormulaDetail::create([
        'formula_id' => test()->formula->id,
        'raw_material_id' => $material->id,
        'quantity' => $quantity,
        'unit_of_measure_id' => ($unit ?? test()->kilogram)->id,
        'step_order' => ++$step,
    ]);
}

function shortageBatch(RawMaterial $material, string|int|float $quantity, ?Warehouse $warehouse = null, string|int|float $unitPrice = 500): InventoryBatch
{
    return InventoryBatch::create([
        'raw_material_id' => $material->id,
        'warehouse_id' => ($warehouse ?? test()->factory)->id,
        'initial_quantity' => $quantity,
        'remaining_quantity' => $quantity,
        'unit_price' => $unitPrice,
        'entry_date' => now(),
    ]);
}

/** @return array<string, mixed> */
function shortageOrderPayload(array $overrides = []): array
{
    return [
        'product_id' => test()->product->id,
        'formula_id' => test()->formula->id,
        'warehouse_id' => test()->factory->id,
        'quantity' => '10',
        'planned_date' => now()->addDay()->toDateString(),
        ...$overrides,
    ];
}

/** Crea e inicia la orden por el flujo real: lo que importa es que nació sin stock. */
function startedOrderWithoutStock(): ProductionOrder
{
    test()->post(route('production-orders.store'), shortageOrderPayload())->assertSessionHasNoErrors();

    $order = ProductionOrder::query()->sole();
    test()->post(route('production-orders.start', $order))->assertSessionHasNoErrors();

    return $order;
}

/** @return array<string, mixed> */
function shortageClosingPayload(ProductionOrderDetail $detail, string $consumed): array
{
    return [
        'actual_yield_quantity' => 10,
        'remnant_quantity_gallons' => 10,
        'density_kg_per_gallon' => 5,
        'quality_responsible_user_id' => test()->user->id,
        'line_adjustment_ids' => [],
        'remnant_consumption_ids' => [],
        'ingredients' => [['id' => $detail->id, 'actual_quantity' => $consumed]],
    ];
}

describe('revisión antes de crear', function () {
    it('no reporta nada con todo el stock', function () {
        $resin = shortageMaterial('S-1');
        shortageFormulaLine($resin, 2);
        shortageBatch($resin, 20);

        $this->postJson(route('production-orders.check-stock'), shortageOrderPayload())
            ->assertOk()
            ->assertExactJson(['shortages' => []]);
    });

    it('lista todas las materias primas que faltan, con sus números y su unidad', function () {
        $resin = shortageMaterial('S-7');
        $pigment = shortageMaterial('S-9');
        $solvent = shortageMaterial('S-3');
        shortageFormulaLine($resin, 2);
        shortageFormulaLine($solvent, 1);
        shortageFormulaLine($pigment, '0.5');
        shortageBatch($resin, 1);
        shortageBatch($solvent, 10);

        $this->postJson(route('production-orders.check-stock'), shortageOrderPayload())
            ->assertOk()
            ->assertExactJson(['shortages' => [
                ['code' => 'S-7', 'unit' => 'kg', 'required' => '20.0000', 'available' => '1.0000', 'missing' => '19.0000'],
                ['code' => 'S-9', 'unit' => 'kg', 'required' => '5.0000', 'available' => '0.0000', 'missing' => '5.0000'],
            ]]);
    });

    it('suma una materia prima que está en dos líneas de la fórmula', function () {
        $resin = shortageMaterial('S-7');
        shortageFormulaLine($resin, 1);
        shortageFormulaLine($resin, '0.5');
        // Cada línea sola (10 y 5) cabe en 12; juntas no.
        shortageBatch($resin, 12);

        $this->postJson(route('production-orders.check-stock'), shortageOrderPayload())
            ->assertExactJson(['shortages' => [
                ['code' => 'S-7', 'unit' => 'kg', 'required' => '15.0000', 'available' => '12.0000', 'missing' => '3.0000'],
            ]]);
    });

    it('no reporta una materia prima sin control de inventario', function () {
        shortageFormulaLine(shortageMaterial('AGUA', ['tracks_inventory' => false]), 5);

        $this->postJson(route('production-orders.check-stock'), shortageOrderPayload())
            ->assertExactJson(['shortages' => []]);
    });

    it('da los números en la unidad de la materia prima aunque la fórmula esté en otra', function () {
        $resin = shortageMaterial('S-7');
        shortageFormulaLine($resin, 250, $this->gram);
        shortageBatch($resin, 1);

        $this->postJson(route('production-orders.check-stock'), shortageOrderPayload())
            ->assertExactJson(['shortages' => [
                ['code' => 'S-7', 'unit' => 'kg', 'required' => '2.5000', 'available' => '1.0000', 'missing' => '1.5000'],
            ]]);
    });

    it('cuenta solo el stock de la bodega de la orden', function () {
        $resin = shortageMaterial('S-7');
        shortageFormulaLine($resin, 1);
        shortageBatch($resin, 100, Warehouse::create(['name' => 'Planta Bogotá', 'city' => 'Bogotá', 'type' => 'factory', 'is_active' => true]));

        $this->postJson(route('production-orders.check-stock'), shortageOrderPayload())
            ->assertExactJson(['shortages' => [
                ['code' => 'S-7', 'unit' => 'kg', 'required' => '10.0000', 'available' => '0.0000', 'missing' => '10.0000'],
            ]]);
    });

    it('usa las reglas de crear la orden: con datos inválidos responde 422', function () {
        $this->postJson(route('production-orders.check-stock'), shortageOrderPayload([
            'quantity' => '',
            'planned_date' => now()->subDays(3)->toDateString(),
            'packaging' => [['product_variant_id' => 999999, 'planned_units' => 1]],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity', 'planned_date', 'packaging.0.product_variant_id']);
    });

    it('responde 422 si una línea de la fórmula no se convierte, igual que al crear', function () {
        shortageFormulaLine(shortageMaterial('S-7'), 1, $this->gallon);

        $this->postJson(route('production-orders.check-stock'), shortageOrderPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['formula_id']);
    });

    it('niega la revisión a quien no puede crear órdenes', function () {
        shortageFormulaLine(shortageMaterial('S-7'), 1);
        actingAsRole(SystemRole::Operator);

        $this->postJson(route('production-orders.check-stock'), shortageOrderPayload())->assertForbidden();
    });

    it('no crea nada', function () {
        shortageFormulaLine(shortageMaterial('S-7'), 1);

        $this->postJson(route('production-orders.check-stock'), shortageOrderPayload())
            ->assertOk()
            ->assertJsonCount(1, 'shortages');

        $this->assertDatabaseCount('production_orders', 0);
        $this->assertDatabaseCount('production_order_details', 0);
    });
});

describe('orden creada sin stock', function () {
    it('estima el costo con el precio de referencia si no hay lotes', function () {
        shortageFormulaLine(shortageMaterial('S-7', ['current_price' => 1234]), 2);

        $this->post(route('production-orders.store'), shortageOrderPayload())->assertSessionHasNoErrors();

        $detail = ProductionOrderDetail::query()->sole();

        expect($detail->unit_cost)->toBe('1234.0000')
            ->and($detail->planned_quantity)->toBe('20.0000')
            ->and($detail->total_cost)->toBe('24680.0000');
    });

    it('no deja completar si el consumo real supera el stock: no descuenta ni cambia de estado', function () {
        $resin = shortageMaterial('S-7');
        shortageFormulaLine($resin, 2);
        $batch = shortageBatch($resin, 1);
        $order = startedOrderWithoutStock();

        $this->from(route('production-orders.show', $order))
            ->post(route('production-orders.complete', $order), shortageClosingPayload($order->details()->sole(), '20'))
            ->assertSessionHasErrors(['ingredients' => "Stock insuficiente de materia prima 'S-7' en finalización. Requerido: 20 kg, faltante: 19 kg."]);

        expect($order->fresh()->status)->toBe(ProductionOrderStatus::InProgress)
            ->and($batch->fresh()->remaining_quantity)->toBe('1.0000')
            ->and(InventoryMovement::query()->where('production_order_id', $order->id)->exists())->toBeFalse();
    });

    it('deja completar si el consumo real sí lo cubre el stock, aunque la fórmula pida más', function () {
        $resin = shortageMaterial('S-7');
        shortageFormulaLine($resin, 2);
        $batch = shortageBatch($resin, 5);
        $order = startedOrderWithoutStock();

        $this->post(route('production-orders.complete', $order), shortageClosingPayload($order->details()->sole(), '4'))
            ->assertSessionHasNoErrors();

        expect($order->fresh()->status)->toBe(ProductionOrderStatus::Completed)
            ->and($batch->fresh()->remaining_quantity)->toBe('1.0000');
    });

    it('deja enviarla a revisión aunque no haya stock: se valida al completar', function () {
        shortageFormulaLine(shortageMaterial('S-7'), 2);
        $order = startedOrderWithoutStock();

        $this->post(route('production-orders.submit-for-review', $order), shortageClosingPayload($order->details()->sole(), '20'))
            ->assertSessionHasNoErrors();

        expect($order->fresh()->status)->toBe(ProductionOrderStatus::PendingReview);
    });
});
