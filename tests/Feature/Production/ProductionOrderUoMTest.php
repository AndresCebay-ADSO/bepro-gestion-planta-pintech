<?php

declare(strict_types=1);

use App\Actions\Production\BuildProductionOrderShowDataAction;
use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\ProductionOrderLineAdjustment;
use App\Models\ProductionOrderPackagingPlan;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Production\ProductionOrderCompletionLines;
use App\Services\Production\ProductionOrderLineUnits;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->user = User::factory()->create([
        'email_verified_at' => now(),
        'job_title' => 'Analista de Calidad',
        'signature_path' => 'signatures/test.png',
    ]);
    test()->seed(RolePermissionSeeder::class);
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->factory = Warehouse::create([
        'id' => 1,
        'name' => 'Planta Cali',
        'city' => 'Cali',
        'type' => 'factory',
        'is_active' => true,
    ]);

    $this->category = ProductCategory::create(['name' => 'General UoM']);

    $this->liter = UnitOfMeasure::create([
        'code' => 'L',
        'name' => 'Litro',
        'symbol' => 'L',
        'to_liter_conversion' => 1,
    ]);

    $this->kilogram = UnitOfMeasure::create([
        'code' => 'KG',
        'name' => 'Kilogramo',
        'symbol' => 'kg',
        'to_kg_conversion' => 1,
    ]);

    $this->gram = UnitOfMeasure::create([
        'code' => 'G',
        'name' => 'Gramo',
        'symbol' => 'g',
        'to_kg_conversion' => 0.001,
    ]);

    $this->product = Product::create([
        'name' => 'Pintura UoM Test',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->liter->id,
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

    // Materia prima en Kilogramos
    $this->material = RawMaterial::create([
        'code' => 'RM-UOM-001',
        'unit_of_measure_id' => $this->kilogram->id,
        'current_price' => 1000,
    ]);

    // Receta en Gramos (250g de materia prima por 1 L de pintura)
    FormulaDetail::create([
        'formula_id' => $this->formula->id,
        'raw_material_id' => $this->material->id,
        'quantity' => 250,
        'unit_of_measure_id' => $this->gram->id,
    ]);

    $this->variant = ProductVariant::create([
        'product_id' => $this->product->id,
        'code' => 'VAR-UOM-001',
        'name' => 'Producto UoM - Galón',
        'unit_of_measure_id' => $this->liter->id,
        'presentation_label' => 'Galón',
        'presentation_value' => 1,
        'is_active' => true,
    ]);
});

test('it creates a production order with normalized planned quantity', function () {
    // 10 kg de stock
    InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 10,
        'remaining_quantity' => 10,
        'unit_price' => 500,
        'entry_date' => now(),
    ]);

    // Se crea una orden para producir 10 L
    // Requerimiento esperado: 250g * 10 = 2500g = 2.5 kg
    $response = $this->post(route('production-orders.store'), [
        'product_id' => $this->product->id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->factory->id,
        'quantity' => 10,
        'planned_date' => now()->addDay()->toDateString(),
        'packaging' => [
            ['product_variant_id' => $this->variant->id, 'planned_units' => 10],
        ],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseCount('production_orders', 1);

    // planned_quantity debe ser 2.5 (en kg) y no 2500 (en g)
    $this->assertDatabaseHas('production_order_details', [
        'raw_material_id' => $this->material->id,
        'planned_quantity' => 2.5000,
        'unit_cost' => 500.0000,
        'total_cost' => 1250.0000, // 2.5 * 500
    ]);
});

/**
 * Crea por HTTP una OP de 10 L con 10 kg de stock: la receta pide 250 g por litro, 2,5 kg en total.
 */
function createUomProductionOrder(): ProductionOrder
{
    InventoryBatch::create([
        'raw_material_id' => test()->material->id,
        'warehouse_id' => test()->factory->id,
        'initial_quantity' => 10,
        'remaining_quantity' => 10,
        'unit_price' => 500,
        'entry_date' => now(),
    ]);

    test()->post(route('production-orders.store'), [
        'product_id' => test()->product->id,
        'formula_id' => test()->formula->id,
        'warehouse_id' => test()->factory->id,
        'quantity' => 10,
        'planned_date' => now()->addDay()->toDateString(),
        'packaging' => [
            ['product_variant_id' => test()->variant->id, 'planned_units' => 10],
        ],
    ])->assertRedirect();

    return ProductionOrder::query()->sole();
}

test('it stores the conversion factor each line was created with', function () {
    $order = createUomProductionOrder();

    // 1 g = 0,001 kg
    expect($order->details()->sole()->conversion_factor)->toBe('0.001000');
});

test('it keeps converting an existing order with its stored factor when the unit changes in the catalog', function () {
    $order = createUomProductionOrder();

    // Alguien corrige la equivalencia del gramo después de crear la OP.
    $this->gram->update(['to_kg_conversion' => '0.002']);

    $detail = app(BuildProductionOrderShowDataAction::class)->execute($order->fresh(), includeCosts: false)['details']->first();

    // Lo que registra el operario se sigue convirtiendo con la equivalencia de la OP, no con la nueva.
    expect(app(ProductionOrderLineUnits::class)->conversionFactors($order->fresh())[$detail['id']])->toBe('0.001000')
        ->and($detail['display_quantity'])->toBe('2500.0000')
        ->and($detail['display_unit'])->toBe('g')
        ->and($detail['planned_quantity'])->toBe('2.5000');
});

test('it creates the order with normalized planned quantities when stock falls short', function () {
    // Solo 1 kg de stock
    InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 1,
        'remaining_quantity' => 1,
        'unit_price' => 500,
        'entry_date' => now(),
    ]);

    // Se intenta crear una orden para producir 10 L (requiere 2.5 kg)
    $response = $this->post(route('production-orders.store'), [
        'product_id' => $this->product->id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->factory->id,
        'quantity' => 10,
        'planned_date' => now()->addDay()->toDateString(),
        'confirm_stock_shortage' => true,
    ]);

    // 1 kg < 2.5 kg ya no impide crearla: lo planeado queda en kg, la unidad de la materia prima.
    $response->assertSessionHasNoErrors();
    $order = ProductionOrder::query()->sole();

    expect($order->status->value)->toBe('pending');
    $this->assertDatabaseHas('production_order_details', [
        'production_order_id' => $order->id,
        'raw_material_id' => $this->material->id,
        'planned_quantity' => 2.5,
    ]);
});

test('it returns a validation error when formula detail unit cannot be converted to raw material unit', function () {
    $incompatibleMaterial = RawMaterial::create([
        'code' => 'RM-UOM-LITER',
        'unit_of_measure_id' => $this->liter->id,
        'current_price' => 1000,
    ]);

    FormulaDetail::create([
        'formula_id' => $this->formula->id,
        'raw_material_id' => $incompatibleMaterial->id,
        'quantity' => 100,
        'unit_of_measure_id' => $this->gram->id,
        'step_order' => 2,
    ]);

    $response = $this->post(route('production-orders.store'), [
        'product_id' => $this->product->id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->factory->id,
        'quantity' => 10,
        'planned_date' => now()->addDay()->toDateString(),
    ]);

    $response->assertSessionHasErrors(['formula_id']);
    $this->assertDatabaseCount('production_orders', 0);
});

test('it falls back to raw material units in show data when formula detail unit cannot be converted', function () {
    $incompatibleMaterial = RawMaterial::create([
        'code' => 'RM-UOM-LITER',
        'unit_of_measure_id' => $this->liter->id,
        'current_price' => 1000,
    ]);

    FormulaDetail::create([
        'formula_id' => $this->formula->id,
        'raw_material_id' => $incompatibleMaterial->id,
        'quantity' => 100,
        'unit_of_measure_id' => $this->gram->id,
        'step_order' => 2,
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-UOM-INCOMPATIBLE',
        'lot_number' => fake()->unique()->numberBetween(100000, 999999),
        'product_id' => $this->product->id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->factory->id,
        'quantity' => 10,
        'status' => 'in_progress',
        'planned_date' => now(),
        'created_by' => $this->user->id,
    ]);

    ProductionOrderDetail::create([
        'production_order_id' => $order->id,
        'raw_material_id' => $incompatibleMaterial->id,
        'batch_id' => null,
        'step_order' => 2,
        'planned_quantity' => 10,
        'unit_cost' => 1000,
        'total_cost' => 10000,
    ]);

    $data = app(BuildProductionOrderShowDataAction::class)->execute($order, includeCosts: false);
    $detail = $data['details']->first();

    expect($detail['display_quantity'])->toBeNull()
        ->and($detail['display_unit'])->toBeNull()
        ->and($detail)->not->toHaveKey('conversion_factor')
        ->and($detail['planned_quantity'])->toBe('10.0000')
        ->and($detail['raw_material']['unit_symbol'])->toBe('L');
});

test('it previews and completes a production order with normalized quantities', function () {
    $batch = InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 10,
        'remaining_quantity' => 10,
        'unit_price' => 500,
        'entry_date' => now(),
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-UOM-TEST',
        'lot_number' => fake()->unique()->numberBetween(100000, 999999),
        'product_id' => $this->product->id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->factory->id,
        'quantity' => 10,
        'status' => 'pending',
        'planned_date' => now(),
        'created_by' => $this->user->id,
    ]);

    // Creamos el detalle ya normalizado (2.5 kg)
    $detail = ProductionOrderDetail::create([
        'production_order_id' => $order->id,
        'raw_material_id' => $this->material->id,
        'batch_id' => null,
        'step_order' => 1,
        'planned_quantity' => 2.5,
        'unit_cost' => 500,
        'total_cost' => 1250,
    ]);

    // 1. Preview Costs
    $previewResponse = $this->post(route('production-orders.preview-costs', $order), [
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 3.0], // 3.0 kg reales gastados
        ],
        'packaging' => [],
    ]);

    $previewResponse->assertOk();
    $previewResponse->assertJsonFragment([
        'unit_cost' => '500.0000',
        'total_cost' => '1500.0000', // 3.0 * 500
    ]);

    // 2. Complete production order
    $this->post(route('production-orders.start', $order));

    $completeResponse = $this->post(route('production-orders.complete', $order), [
        'actual_yield_quantity' => 10,
        'remnant_quantity_gallons' => 10,
        'viscosity_ku' => 100,
        'grinding_hg' => 6,
        'responsible_name' => 'Operario UoM',
        'density_kg_per_gallon' => 5,
        'quality_responsible_user_id' => $this->user->id,
        'line_adjustment_ids' => [],
        'remnant_consumption_ids' => [],
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 3.0], // 3.0 kg reales gastados
        ],
        'packaging' => [],
    ]);

    $completeResponse->assertRedirect();
    $order->refresh();
    expect($order->status->value)->toBe('completed');

    $batch->refresh();
    // Debe haber descontado 3 kg
    expect((float) $batch->remaining_quantity)->toBe(7.0);

    $this->assertDatabaseHas('inventory_movements', [
        'production_order_id' => $order->id,
        'type' => 'exit',
        'quantity' => 3.0000,
    ]);

    $detail->refresh();
    expect((float) $detail->actual_quantity)->toBe(3.0);
    expect((float) $detail->total_cost)->toBe(1500.0);
});

/**
 * Crea por HTTP y deja en proceso una OP de 250 galones de una fórmula que pide `$quantityPerGallon` de una materia prima
 * en kilos, con 1 kg en un solo lote a $200.000 el kilo.
 *
 * @return array{order: ProductionOrder, batch: InventoryBatch, detail: ProductionOrderDetail, plan: ProductionOrderPackagingPlan}
 */
function startedOrderInMaterialKilos(string $quantityPerGallon, UnitOfMeasure $formulaUnit): array
{
    $product = Product::create([
        'name' => 'Pintura en gramos',
        'category_id' => test()->category->id,
        'unit_of_measure_id' => test()->liter->id,
        'current_cost' => 10,
        'cif_percentage' => 0,
        'current_price' => 10,
        'price_threshold' => 0,
    ]);
    $formula = Formula::create([
        'product_id' => $product->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => test()->user->id,
    ]);
    $material = RawMaterial::create([
        'code' => 'RM-UOM-GRAM',
        'unit_of_measure_id' => test()->kilogram->id,
        'current_price' => 200000,
    ]);
    FormulaDetail::create([
        'formula_id' => $formula->id,
        'raw_material_id' => $material->id,
        'quantity' => $quantityPerGallon,
        'unit_of_measure_id' => $formulaUnit->id,
        'step_order' => 1,
    ]);
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'code' => 'VAR-UOM-GRAM',
        'name' => 'Pintura en gramos - Galón',
        'unit_of_measure_id' => test()->liter->id,
        'presentation_label' => 'Galón',
        'presentation_value' => 1,
        'is_active' => true,
    ]);
    $batch = InventoryBatch::create([
        'raw_material_id' => $material->id,
        'warehouse_id' => test()->factory->id,
        'initial_quantity' => 1,
        'remaining_quantity' => 1,
        'unit_price' => 200000,
        'entry_date' => now(),
    ]);

    test()->post(route('production-orders.store'), [
        'product_id' => $product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => test()->factory->id,
        'quantity' => 250,
        'planned_date' => now()->addDay()->toDateString(),
        'packaging' => [
            ['product_variant_id' => $variant->id, 'planned_units' => 250],
        ],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $order = ProductionOrder::query()->sole();
    test()->post(route('production-orders.start', $order))->assertSessionHasNoErrors();

    return [
        'order' => $order->fresh(),
        'batch' => $batch,
        'detail' => $order->details()->sole(),
        'plan' => $order->packagingPlans()->sole(),
    ];
}

/**
 * Lo que envía la pantalla al enviar a revisión o completar, con `$typed` como consumo real de la única línea.
 *
 * @param  array{detail: ProductionOrderDetail, plan: ProductionOrderPackagingPlan}  $line
 * @return array<string, mixed>
 */
function closingPayload(array $line, string $typed): array
{
    return [
        'actual_yield_quantity' => 250,
        'viscosity_ku' => 100,
        'grinding_hg' => 6,
        'responsible_name' => 'Operario UoM',
        'density_kg_per_gallon' => 5,
        'quality_responsible_user_id' => test()->user->id,
        'line_adjustment_ids' => [],
        'remnant_consumption_ids' => [],
        'ingredients' => [['id' => $line['detail']->id, 'actual_quantity' => $typed]],
        'packaging' => [['id' => $line['plan']->id, 'actual_units' => 250]],
    ];
}

test('it plans an order of a formula in grams in the unit of the raw material', function () {
    $line = startedOrderInMaterialKilos('0.15', $this->gram);

    // 0,15 g por galón × 250 galones = 37,5 g.
    expect($line['detail']->planned_quantity)->toBe('0.0375');
});

test('completing converts what was typed in grams to the unit of the raw material', function () {
    $line = startedOrderInMaterialKilos('0.15', $this->gram);

    $this->post(route('production-orders.complete', $line['order']), closingPayload($line, '37.5'))
        ->assertRedirect()->assertSessionHasNoErrors();

    $detail = $line['detail']->fresh();

    expect($line['order']->fresh()->status->value)->toBe('completed')
        ->and($line['batch']->fresh()->remaining_quantity)->toBe('0.9625')
        ->and(InventoryMovement::query()->where('production_order_id', $line['order']->id)->sole()->quantity)->toBe('0.0375')
        ->and($detail->actual_quantity)->toBe('0.0375')
        ->and($detail->total_cost)->toBe('7500.0000');
});

test('submitting for review stores the converted quantity and shows back what was typed', function () {
    $line = startedOrderInMaterialKilos('0.15', $this->gram);

    $this->post(route('production-orders.submit-for-review', $line['order']), closingPayload($line, '37.5'))
        ->assertRedirect()->assertSessionHasNoErrors();

    $row = app(BuildProductionOrderShowDataAction::class)->execute($line['order']->fresh(), includeCosts: false)['details']->first();

    expect($line['detail']->fresh()->actual_quantity)->toBe('0.0375')
        ->and($row)->not->toHaveKey('actual_quantity')
        ->and($row['actual_display_quantity'])->toBe('37.5000')
        ->and($row['display_unit'])->toBe('g')
        ->and($row)->not->toHaveKey('conversion_factor');
});

test('the cost preview converts what was typed in grams without saving anything', function () {
    $line = startedOrderInMaterialKilos('0.15', $this->gram);

    $this->postJson(route('production-orders.preview-costs', $line['order']), [
        'ingredients' => [['id' => $line['detail']->id, 'actual_quantity' => '37.5']],
        'packaging' => [['id' => $line['plan']->id, 'actual_units' => 250]],
    ])->assertOk()->assertJsonPath('ingredients.0.total_cost', '7500.0000');

    expect($line['detail']->fresh()->actual_quantity)->toBeNull()
        ->and($line['batch']->fresh()->remaining_quantity)->toBe('1.0000')
        ->and(InventoryMovement::query()->where('production_order_id', $line['order']->id)->exists())->toBeFalse();
});

test('completing after review does not convert twice', function () {
    $line = startedOrderInMaterialKilos('0.15', $this->gram);

    $this->post(route('production-orders.submit-for-review', $line['order']), closingPayload($line, '37.5'))
        ->assertSessionHasNoErrors();

    // Quien aprueba abre la orden y confirma lo que la pantalla le muestra.
    $shown = app(BuildProductionOrderShowDataAction::class)->execute($line['order']->fresh(), includeCosts: false)['details']->first();

    $this->post(route('production-orders.complete', $line['order']), closingPayload($line, $shown['actual_display_quantity']))
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($line['order']->fresh()->status->value)->toBe('completed')
        ->and($line['detail']->fresh()->actual_quantity)->toBe('0.0375')
        ->and($line['batch']->fresh()->remaining_quantity)->toBe('0.9625');
});

test('a line with a factor but no unit from the formula takes what was typed in the raw material unit, like the screen', function () {
    $line = startedOrderInMaterialKilos('0.15', $this->gram);

    // La línea guardó su factor, pero ya no hay línea de la fórmula de donde sacar la unidad.
    $line['order']->formula->details()->delete();

    $this->post(route('production-orders.submit-for-review', $line['order']), closingPayload($line, '37.5'))
        ->assertRedirect()->assertSessionHasNoErrors();

    $row = app(BuildProductionOrderShowDataAction::class)->execute($line['order']->fresh(), includeCosts: false)['details']->first();

    expect($line['detail']->fresh()->conversion_factor)->toBe('0.001000')
        ->and($line['detail']->fresh()->actual_quantity)->toBe('37.5000')
        ->and($row['display_unit'])->toBeNull()
        ->and($row['actual_display_quantity'])->toBe('37.5000');
});

test('a line in kilos keeps what was typed', function () {
    $line = startedOrderInMaterialKilos('0.0007', $this->kilogram);

    expect($line['detail']->conversion_factor)->toBe('1.000000');

    $this->post(route('production-orders.submit-for-review', $line['order']), closingPayload($line, '0.17'))
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($line['detail']->fresh()->actual_quantity)->toBe('0.1700');
});

// Lo digitado que queda en 0 no descontaría inventario ni sumaría costo: se rechaza para que alguien lo decida.
test('it rejects a consumption that would round down to zero when saving', function (string $route, string $status) {
    $line = startedOrderInMaterialKilos('0.15', $this->gram);

    $this->post(route($route, $line['order']), closingPayload($line, '0.04'))
        ->assertSessionHasErrors([
            'ingredients' => 'RM-UOM-GRAM: 0,04 g es menos de lo mínimo que se registra (0,1 g). Digita 0,1 g o, si no se usó, 0.',
        ]);

    expect($line['order']->fresh()->status->value)->toBe($status)
        ->and($line['detail']->fresh()->actual_quantity)->toBeNull()
        ->and((float) $line['batch']->fresh()->remaining_quantity)->toBe(1.0)
        ->and(InventoryMovement::query()->where('production_order_id', $line['order']->id)->exists())->toBeFalse();
})->with([
    'al completar' => ['production-orders.complete', 'in_progress'],
    'al enviar a revisión' => ['production-orders.submit-for-review', 'in_progress'],
]);

test('it records zero when it is typed on purpose', function () {
    $line = startedOrderInMaterialKilos('0.15', $this->gram);

    $this->post(route('production-orders.complete', $line['order']), closingPayload($line, '0'))
        ->assertSessionHasNoErrors();

    expect($line['order']->fresh()->status->value)->toBe('completed')
        ->and((float) $line['detail']->fresh()->actual_quantity)->toBe(0.0)
        ->and((float) $line['batch']->fresh()->remaining_quantity)->toBe(1.0);
});

test('it records the smallest quantity that does not round down to zero', function () {
    $line = startedOrderInMaterialKilos('0.15', $this->gram);

    $this->post(route('production-orders.complete', $line['order']), closingPayload($line, '0.05'))
        ->assertSessionHasNoErrors();

    expect($line['detail']->fresh()->actual_quantity)->toBe('0.0001')
        ->and($line['batch']->fresh()->remaining_quantity)->toBe('0.9999');
});

test('it does not reject that quantity in the cost preview, while typing', function () {
    $line = startedOrderInMaterialKilos('0.15', $this->gram);

    $this->postJson(route('production-orders.preview-costs', $line['order']), [
        'ingredients' => [['id' => $line['detail']->id, 'actual_quantity' => '0.04']],
        'packaging' => [],
    ])->assertOk()->assertJsonPath('ingredients.0.total_cost', '0.0000');
});

// Una unidad creada en el catálogo puede dar un factor de 0,00004: gramos a un saco de 25 kg.
test('it opens an order whose stored factor is smaller than four decimals', function () {
    $line = startedOrderInMaterialKilos('0.15', $this->gram);
    $line['detail']->update(['conversion_factor' => '0.000040', 'actual_quantity' => '0.0200']);

    $row = app(BuildProductionOrderShowDataAction::class)->execute($line['order']->fresh(), includeCosts: false)['details'][0];

    expect($row['actual_display_quantity'])->toBe('500.0000');
});

test('it warns about the minimum with a factor smaller than four decimals', function () {
    $line = startedOrderInMaterialKilos('0.15', $this->gram);
    $line['detail']->update(['conversion_factor' => '0.000040']);

    $this->post(route('production-orders.complete', $line['order']), closingPayload($line, '0.5'))
        ->assertSessionHasErrors([
            'ingredients' => 'RM-UOM-GRAM: 0,5 g es menos de lo mínimo que se registra (2,5 g). Digita 2,5 g o, si no se usó, 0.',
        ]);
});

test('it warns that the order changed before any quantity error', function (string $route) {
    $line = startedOrderInMaterialKilos('0.15', $this->gram);
    ProductionOrderLineAdjustment::create([
        'production_order_id' => $line['order']->id,
        'raw_material_id' => $line['detail']->raw_material_id,
        'quantity' => '1',
        'reason' => 'Corrección de viscosidad',
        'created_by' => $this->user->id,
    ]);

    $this->post(route($route, $line['order']), closingPayload($line, '0.04'))
        ->assertSessionHasErrors(['line_adjustment_ids' => __(ProductionOrderCompletionLines::ORDER_CHANGED_MESSAGE)])
        ->assertSessionDoesntHaveErrors('ingredients');
})->with(['production-orders.complete', 'production-orders.submit-for-review']);
