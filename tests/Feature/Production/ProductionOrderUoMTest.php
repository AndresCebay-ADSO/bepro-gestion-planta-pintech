<?php

declare(strict_types=1);

use App\Actions\Production\BuildProductionOrderShowDataAction;
use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->adminRole = Role::firstOrCreate(['name' => 'admin']);
    $this->user->assignRole($this->adminRole);
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
        'code' => 'P-UOM-001',
        'name' => 'Pintura UoM Test',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->liter->id,
        'current_cost' => 10,
        'profit_margin' => 0,
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
        'sku' => 'VAR-UOM-001',
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

test('it validates stock using normalized quantities', function () {
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
    ]);

    // Debe fallar porque 1 kg < 2.5 kg
    $response->assertSessionHasErrors(['product_id']);
    $this->assertDatabaseCount('production_orders', 0);
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
        ->and($detail['conversion_factor'])->toBeNull()
        ->and($detail['planned_quantity'])->toBe(10.0)
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
        'viscosity_ku' => 100,
        'grinding_hg' => 6,
        'responsible_name' => 'Operario UoM',
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
