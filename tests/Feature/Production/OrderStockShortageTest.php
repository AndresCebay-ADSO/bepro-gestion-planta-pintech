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
use Inertia\Testing\AssertableInertia as Assert;

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
    test()->post(route('production-orders.store'), shortageOrderPayload(['confirm_stock_shortage' => true]))->assertSessionHasNoErrors();

    $order = ProductionOrder::query()->sole();
    test()->post(route('production-orders.start', $order))->assertSessionHasNoErrors();

    return $order;
}

/** @return array<string, mixed> */
function shortageClosingPayload(ProductionOrderDetail $detail, string $consumed): array
{
    return [
        'actual_yield_quantity' => 10,
        'remnant_quantity_kg' => 50,
        'density_kg_per_gallon' => 5,
        'quality_responsible_user_id' => test()->user->id,
        'line_adjustment_ids' => [],
        'remnant_consumption_ids' => [],
        'ingredients' => [['id' => $detail->id, 'actual_quantity' => $consumed]],
    ];
}

/** @return array<string, mixed> */
function expectedShortage(string $code, string $required, string $available, string $missing, string $unit = 'kg', bool $hasPrice = true): array
{
    return [
        'code' => $code,
        'unit' => $unit,
        'required' => $required,
        'available' => $available,
        'missing' => $missing,
        'has_reference_price' => $hasPrice,
    ];
}

/** Envía el formulario sin aceptar los faltantes: vuelve a la pantalla, con la lista y sin guardar nada. */
function postOrderExpectingShortages(array $shortages): void
{
    test()->from(route('production-orders.create'))
        ->post(route('production-orders.store'), shortageOrderPayload())
        ->assertRedirect(route('production-orders.create'))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('stockShortages', $shortages);

    test()->assertDatabaseCount('production_orders', 0);
    test()->assertDatabaseCount('production_order_details', 0);
}

describe('al enviar sin aceptar los faltantes', function () {
    it('crea la orden sin preguntar si hay todo el stock', function () {
        $resin = shortageMaterial('S-1');
        shortageFormulaLine($resin, 2);
        shortageBatch($resin, 20);

        $this->post(route('production-orders.store'), shortageOrderPayload())
            ->assertRedirect(route('production-orders.show', ProductionOrder::query()->sole()))
            ->assertInertiaFlashMissing('stockShortages');
    });

    it('lista todas las materias primas que faltan, con sus números y su unidad, y no guarda nada', function () {
        $resin = shortageMaterial('S-7');
        $pigment = shortageMaterial('S-9');
        $solvent = shortageMaterial('S-3');
        shortageFormulaLine($resin, 2);
        shortageFormulaLine($solvent, 1);
        shortageFormulaLine($pigment, '0.5');
        shortageBatch($resin, 1);
        shortageBatch($solvent, 10);

        postOrderExpectingShortages([
            expectedShortage('S-7', '20.0000', '1.0000', '19.0000'),
            expectedShortage('S-9', '5.0000', '0.0000', '5.0000'),
        ]);
    });

    it('la lista llega a la pantalla del formulario', function () {
        shortageFormulaLine(shortageMaterial('S-7'), 1);

        $this->from(route('production-orders.create'))
            ->followingRedirects()
            ->post(route('production-orders.store'), shortageOrderPayload())
            ->assertInertia(fn (Assert $page) => $page
                ->component('Production/Orders/Create')
                ->hasFlash('stockShortages', [expectedShortage('S-7', '10.0000', '0.0000', '10.0000')]));
    });

    it('suma una materia prima que está en dos líneas de la fórmula', function () {
        $resin = shortageMaterial('S-7');
        shortageFormulaLine($resin, 1);
        shortageFormulaLine($resin, '0.5');
        // Cada línea sola (10 y 5) cabe en 12; juntas no.
        shortageBatch($resin, 12);

        postOrderExpectingShortages([expectedShortage('S-7', '15.0000', '12.0000', '3.0000')]);
    });

    it('no pregunta por una materia prima sin control de inventario', function () {
        shortageFormulaLine(shortageMaterial('AGUA', ['tracks_inventory' => false]), 5);

        $this->post(route('production-orders.store'), shortageOrderPayload())
            ->assertInertiaFlashMissing('stockShortages');

        $this->assertDatabaseCount('production_orders', 1);
    });

    it('da los números en la unidad de la materia prima aunque la fórmula esté en otra', function () {
        $resin = shortageMaterial('S-7');
        shortageFormulaLine($resin, 250, $this->gram);
        shortageBatch($resin, 1);

        postOrderExpectingShortages([expectedShortage('S-7', '2.5000', '1.0000', '1.5000')]);
    });

    it('cuenta solo el stock de la bodega de la orden', function () {
        $resin = shortageMaterial('S-7');
        shortageFormulaLine($resin, 1);
        shortageBatch($resin, 100, Warehouse::create(['name' => 'Planta Bogotá', 'city' => 'Bogotá', 'type' => 'factory', 'is_active' => true]));

        postOrderExpectingShortages([expectedShortage('S-7', '10.0000', '0.0000', '10.0000')]);
    });

    it('marca la materia prima que no tiene precio de referencia', function () {
        shortageFormulaLine(shortageMaterial('S-7', ['current_price' => null]), 1);
        shortageFormulaLine(shortageMaterial('S-9', ['current_price' => 0]), 1);

        postOrderExpectingShortages([
            expectedShortage('S-7', '10.0000', '0.0000', '10.0000', hasPrice: false),
            expectedShortage('S-9', '10.0000', '0.0000', '10.0000', hasPrice: false),
        ]);
    });

    it('no llega a preguntar si los datos son inválidos', function () {
        shortageFormulaLine(shortageMaterial('S-7'), 1);

        $this->post(route('production-orders.store'), shortageOrderPayload([
            'quantity' => '',
            'confirm_stock_shortage' => 'quizás',
        ]))
            ->assertSessionHasErrors(['quantity', 'confirm_stock_shortage'])
            ->assertInertiaFlashMissing('stockShortages');
    });

    it('no llega a preguntar si una línea de la fórmula no se convierte', function () {
        shortageFormulaLine(shortageMaterial('S-7'), 1, $this->gallon);

        $this->post(route('production-orders.store'), shortageOrderPayload())
            ->assertSessionHasErrors(['formula_id'])
            ->assertInertiaFlashMissing('stockShortages');
    });

    it('no llega a preguntar si la cantidad de una línea no cabe en el registro', function () {
        shortageFormulaLine(shortageMaterial('S-7'), '99999999');

        $this->post(route('production-orders.store'), shortageOrderPayload())
            ->assertSessionHasErrors(['quantity'])
            ->assertInertiaFlashMissing('stockShortages');

        $this->assertDatabaseCount('production_orders', 0);
    });

    it('no se la muestra a quien no puede crear órdenes', function () {
        shortageFormulaLine(shortageMaterial('S-7'), 1);
        actingAsRole(SystemRole::Operator);

        $this->post(route('production-orders.store'), shortageOrderPayload())
            ->assertForbidden()
            ->assertInertiaFlashMissing('stockShortages');
    });
});

describe('al aceptar los faltantes', function () {
    it('crea la orden, sin saltarse el número que le tocaba', function () {
        shortageFormulaLine(shortageMaterial('S-7'), 1);

        $this->from(route('production-orders.create'))->post(route('production-orders.store'), shortageOrderPayload());

        $this->post(route('production-orders.store'), shortageOrderPayload(['confirm_stock_shortage' => true]))
            ->assertRedirect(route('production-orders.show', ProductionOrder::query()->sole()));

        $order = ProductionOrder::query()->sole();

        expect($order->status)->toBe(ProductionOrderStatus::Pending)
            ->and($order->order_number)->toEndWith('-0001')
            ->and($order->lot_number)->toBe((int) config('production.lot_start_number', 1));
    });
});

describe('costo estimado', function () {
    /** Crea la orden aceptando los faltantes y devuelve su línea. */
    function estimatedLine(): ProductionOrderDetail
    {
        test()->post(route('production-orders.store'), shortageOrderPayload(['confirm_stock_shortage' => true]))->assertSessionHasNoErrors();

        return ProductionOrderDetail::query()->sole();
    }

    it('con varios lotes, costea cada parte al precio de su lote', function () {
        $resin = shortageMaterial('S-7', ['current_price' => 9999]);
        shortageFormulaLine($resin, 2);
        shortageBatch($resin, 10, unitPrice: 5000);
        shortageBatch($resin, 10, unitPrice: 5100);

        $detail = estimatedLine();

        expect($detail->unit_cost)->toBe('5050.0000')
            ->and($detail->total_cost)->toBe('101000.0000');
    });

    it('lo que no alcanzan los lotes se costea al precio de referencia, no al del lote que quedaba', function () {
        $resin = shortageMaterial('S-7', ['current_price' => 5000]);
        shortageFormulaLine($resin, 2);
        shortageBatch($resin, 1, unitPrice: 1000);

        $detail = estimatedLine();

        expect($detail->unit_cost)->toBe('4800.0000')
            ->and($detail->total_cost)->toBe('96000.0000');
    });

    it('sin precio de referencia, lo que falta no suma', function () {
        $resin = shortageMaterial('S-7', ['current_price' => null]);
        shortageFormulaLine($resin, 2);
        shortageBatch($resin, 1, unitPrice: 1000);

        $detail = estimatedLine();

        expect($detail->unit_cost)->toBe('50.0000')
            ->and($detail->total_cost)->toBe('1000.0000');
    });

    it('sin lotes ni precio de referencia, queda en 0', function () {
        shortageFormulaLine(shortageMaterial('S-7', ['current_price' => null]), 2);

        expect(estimatedLine()->total_cost)->toBe('0.0000');
    });
});

describe('orden creada sin stock', function () {
    it('estima el costo con el precio de referencia si no hay lotes', function () {
        shortageFormulaLine(shortageMaterial('S-7', ['current_price' => 1234]), 2);

        $this->post(route('production-orders.store'), shortageOrderPayload(['confirm_stock_shortage' => true]))->assertSessionHasNoErrors();

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
