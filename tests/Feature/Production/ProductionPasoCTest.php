<?php

declare(strict_types=1);

use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\ProductionOrderPackagingPlan;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole(Role::create(['name' => 'admin']));
    $this->actingAs($this->user);

    // Planta Cali (ID 1 es forzado en el servicio, así que lo creamos así)
    $this->factory = Warehouse::create([
        'id' => 1,
        'name' => 'Planta Cali',
        'city' => 'Cali',
        'type' => 'factory',
        'is_active' => true,
    ]);

    $category = ProductCategory::create(['name' => 'General']);
    $uom = UnitOfMeasure::create(['code' => 'L', 'name' => 'Litro', 'symbol' => 'L']);

    $product = Product::create([
        'code' => 'P-001',
        'name' => 'Pintura Test',
        'category_id' => $category->id,
        'unit_of_measure_id' => $uom->id,
        'current_cost' => 10,
        'profit_margin' => 0,
        'current_price' => 10,
        'price_threshold' => 0,
    ]);

    $this->formula = Formula::create([
        'product_id' => $product->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $this->material = RawMaterial::create([
        'code' => 'RM-001',
        'unit_of_measure_id' => $uom->id,
        'current_price' => 5000,
    ]);

    FormulaDetail::create([
        'formula_id' => $this->formula->id,
        'raw_material_id' => $this->material->id,
        'quantity' => 0.5,
        'unit_of_measure_id' => $uom->id,
    ]);

    ProductVariant::create([
        'product_id' => $product->id,
        'sku' => 'VAR-001',
        'unit_of_measure_id' => $uom->id,
        'presentation_label' => 'Galón',
        'is_active' => true,
    ]);
});

test('it fails to create order if stock is insufficient', function () {
    $response = $this->post(route('production-orders.store'), [
        'product_id' => $this->formula->product_id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->factory->id,
        'quantity' => 100, // Requiere 50L de resina
        'planned_date' => now()->addDay()->toDateString(),
    ]);

    $response->assertSessionHasErrors(['product_id']);
    $this->assertDatabaseCount('production_orders', 0);
});

test('it allows order creation if stock is sufficient', function () {
    InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 5,
        'entry_date' => now(),
    ]);

    $variant = ProductVariant::where('product_id', $this->formula->product_id)->first();

    $response = $this->post(route('production-orders.store'), [
        'product_id' => $this->formula->product_id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->factory->id,
        'quantity' => 100,
        'planned_date' => now()->addDay()->toDateString(),
        'packaging' => [
            ['product_variant_id' => $variant->id, 'planned_units' => 20],
        ],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseCount('production_orders', 1);

    // Verificar que se crearon los detalles de ingredientes
    $this->assertDatabaseCount('production_order_details', 1);
    $this->assertDatabaseHas('production_order_details', [
        'raw_material_id' => $this->material->id,
        'planned_quantity' => 50, // 0.5 * 100
    ]);

    // Verificar que se creó el plan de envasado
    $this->assertDatabaseCount('production_order_packaging_plan', 1);
    $this->assertDatabaseHas('production_order_packaging_plan', [
        'product_variant_id' => $variant->id,
        'planned_units' => 20,
    ]);
});

test('it completes order and updates inventory', function () {
    $batch = InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 5,
        'entry_date' => now(),
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-PROD',
        'product_id' => $this->formula->product_id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->factory->id,
        'quantity' => 100,
        'status' => 'pending',
        'planned_date' => now(),
        'created_by' => $this->user->id,
    ]);

    $detail = ProductionOrderDetail::create([
        'production_order_id' => $order->id,
        'raw_material_id' => $this->material->id,
        'batch_id' => $batch->id,
        'planned_quantity' => 50,
        'unit_cost' => 5,
        'total_cost' => 250,
    ]);

    $variant = ProductVariant::where('product_id', $order->product_id)->first();
    $pack = ProductionOrderPackagingPlan::create([
        'production_order_id' => $order->id,
        'product_variant_id' => $variant->id,
        'planned_units' => 20,
    ]);

    $response = $this->post(route('production-orders.complete', $order), [
        'actual_yield_quantity' => 95,
        'viscosity_ku' => 105,
        'grinding_hg' => 7,
        'responsible_name' => 'Operario Juan',
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 52],
        ],
        'packaging' => [
            ['id' => $pack->id, 'actual_units' => 19],
        ],
    ]);

    $response->assertRedirect();

    $order->refresh();
    expect($order->status->value)->toBe('completed');
    expect((float) $order->viscosity_ku)->toBe(105.0);

    $batch->refresh();
    expect((float) $batch->remaining_quantity)->toBe(48.0);

    $this->assertDatabaseHas('inventory_movements', [
        'production_order_id' => $order->id,
        'type' => 'exit',
        'quantity' => 52,
    ]);

    $this->assertDatabaseHas('finished_inventories', [
        'product_id' => $order->product_id,
        'product_variant_id' => $variant->id,
        'quantity' => 19,
    ]);
});

test('it completes order even when there is no packaging plan', function () {
    $batch = InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 60,
        'remaining_quantity' => 60,
        'unit_price' => 5,
        'entry_date' => now(),
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-NOPACK',
        'product_id' => $this->formula->product_id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->factory->id,
        'quantity' => 100,
        'status' => 'pending',
        'planned_date' => now(),
        'created_by' => $this->user->id,
    ]);

    $detail = ProductionOrderDetail::create([
        'production_order_id' => $order->id,
        'raw_material_id' => $this->material->id,
        'batch_id' => $batch->id,
        'planned_quantity' => 50,
        'unit_cost' => 5,
        'total_cost' => 250,
    ]);

    $response = $this->post(route('production-orders.complete', $order), [
        'actual_yield_quantity' => 98,
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 50],
        ],
    ]);

    $response->assertRedirect();
    $order->refresh();

    expect($order->status->value)->toBe('completed');
    $this->assertDatabaseCount('finished_inventory_movements', 0);
});

test('it rejects creating order in non-factory warehouse', function () {
    InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 5,
        'entry_date' => now(),
    ]);

    $storageWarehouse = Warehouse::create([
        'name' => 'Bodega Medellin',
        'city' => 'Medellin',
        'type' => 'storage',
        'is_active' => true,
    ]);

    $response = $this->post(route('production-orders.store'), [
        'product_id' => $this->formula->product_id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $storageWarehouse->id,
        'quantity' => 50,
        'planned_date' => now()->addDay()->toDateString(),
    ]);

    $response->assertSessionHasErrors(['warehouse_id']);
    $this->assertDatabaseCount('production_orders', 0);
});

test('it shows production order detail with loaded data for the view', function () {
    $batch = InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 5,
        'entry_date' => now(),
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-VIEW-0001',
        'product_id' => $this->formula->product_id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->factory->id,
        'quantity' => 100,
        'status' => 'pending',
        'planned_date' => now(),
        'created_by' => $this->user->id,
    ]);

    ProductionOrderDetail::create([
        'production_order_id' => $order->id,
        'raw_material_id' => $this->material->id,
        'batch_id' => $batch->id,
        'planned_quantity' => 50,
        'unit_cost' => 5,
        'total_cost' => 250,
    ]);

    $variant = ProductVariant::where('product_id', $order->product_id)->first();
    ProductionOrderPackagingPlan::create([
        'production_order_id' => $order->id,
        'product_variant_id' => $variant->id,
        'planned_units' => 20,
    ]);

    $response = $this->get(route('production-orders.show', $order));

    $response->assertOk();
    $response->assertSee('Production/Orders/Show');
    $response->assertSee('OP-VIEW-0001');
    $response->assertSee('Pintura Test');
    $response->assertSee('packaging_plans');
});

test('it aggregates finished inventory by product and warehouse when packaging has multiple variants', function () {
    $batch = InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 200,
        'remaining_quantity' => 200,
        'unit_price' => 5,
        'entry_date' => now(),
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-MULTI-VAR',
        'product_id' => $this->formula->product_id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->factory->id,
        'quantity' => 100,
        'status' => 'pending',
        'planned_date' => now(),
        'created_by' => $this->user->id,
    ]);

    $detail = ProductionOrderDetail::create([
        'production_order_id' => $order->id,
        'raw_material_id' => $this->material->id,
        'batch_id' => $batch->id,
        'planned_quantity' => 50,
        'unit_cost' => 5,
        'total_cost' => 250,
    ]);

    $firstVariant = ProductVariant::where('product_id', $order->product_id)->first();
    $secondVariant = ProductVariant::create([
        'product_id' => $order->product_id,
        'sku' => 'VAR-002',
        'unit_of_measure_id' => $firstVariant->unit_of_measure_id,
        'presentation_label' => 'Cuarto',
        'is_active' => true,
    ]);

    $firstPack = ProductionOrderPackagingPlan::create([
        'production_order_id' => $order->id,
        'product_variant_id' => $firstVariant->id,
        'planned_units' => 10,
    ]);

    $secondPack = ProductionOrderPackagingPlan::create([
        'production_order_id' => $order->id,
        'product_variant_id' => $secondVariant->id,
        'planned_units' => 10,
    ]);

    $response = $this->post(route('production-orders.complete', $order), [
        'actual_yield_quantity' => 95,
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 50],
        ],
        'packaging' => [
            ['id' => $firstPack->id, 'actual_units' => 10],
            ['id' => $secondPack->id, 'actual_units' => 10],
        ],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseCount('finished_inventories', 1);
    $this->assertDatabaseHas('finished_inventories', [
        'product_id' => $order->product_id,
        'warehouse_id' => $order->warehouse_id,
        'quantity' => 20,
    ]);
    $this->assertDatabaseCount('finished_inventory_movements', 2);
});

test('it consumes raw material using fifo across multiple batches', function () {
    $oldestBatch = InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 5,
        'entry_date' => now()->subDays(3),
    ]);

    $middleBatch = InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 6,
        'entry_date' => now()->subDays(2),
    ]);

    InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 7,
        'entry_date' => now()->subDay(),
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-FIFO-001',
        'product_id' => $this->formula->product_id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->factory->id,
        'quantity' => 100,
        'status' => 'pending',
        'planned_date' => now(),
        'created_by' => $this->user->id,
    ]);

    $detail = ProductionOrderDetail::create([
        'production_order_id' => $order->id,
        'raw_material_id' => $this->material->id,
        'batch_id' => $oldestBatch->id,
        'planned_quantity' => 100,
        'unit_cost' => 5,
        'total_cost' => 500,
    ]);

    $response = $this->post(route('production-orders.complete', $order), [
        'actual_yield_quantity' => 95,
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 150],
        ],
        'packaging' => [],
    ]);

    $response->assertRedirect();

    $oldestBatch->refresh();
    $middleBatch->refresh();

    expect((float) $oldestBatch->remaining_quantity)->toBe(0.0);
    expect((float) $middleBatch->remaining_quantity)->toBe(50.0);

    $this->assertDatabaseHas('inventory_movements', [
        'production_order_id' => $order->id,
        'batch_id' => $oldestBatch->id,
        'quantity' => 100,
        'type' => 'exit',
    ]);

    $this->assertDatabaseHas('inventory_movements', [
        'production_order_id' => $order->id,
        'batch_id' => $middleBatch->id,
        'quantity' => 50,
        'type' => 'exit',
    ]);
});

test('it consumes packaging raw material when finishing production by variant units', function () {
    $formulaBatch = InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 120,
        'remaining_quantity' => 120,
        'unit_price' => 5,
        'entry_date' => now()->subDays(2),
    ]);

    $packagingRawMaterial = RawMaterial::create([
        'code' => 'ENV-001',
        'unit_of_measure_id' => $this->material->unit_of_measure_id,
        'current_price' => 1200,
    ]);

    $packagingBatch = InventoryBatch::create([
        'raw_material_id' => $packagingRawMaterial->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 50,
        'remaining_quantity' => 50,
        'unit_price' => 1.2,
        'entry_date' => now()->subDay(),
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-ENV-001',
        'product_id' => $this->formula->product_id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->factory->id,
        'quantity' => 100,
        'status' => 'pending',
        'planned_date' => now(),
        'created_by' => $this->user->id,
    ]);

    $detail = ProductionOrderDetail::create([
        'production_order_id' => $order->id,
        'raw_material_id' => $this->material->id,
        'batch_id' => $formulaBatch->id,
        'planned_quantity' => 50,
        'unit_cost' => 5,
        'total_cost' => 250,
    ]);

    $variant = ProductVariant::where('product_id', $order->product_id)->first();
    $variant->update(['package_raw_material_id' => $packagingRawMaterial->id]);

    $pack = ProductionOrderPackagingPlan::create([
        'production_order_id' => $order->id,
        'product_variant_id' => $variant->id,
        'planned_units' => 20,
    ]);

    $response = $this->post(route('production-orders.complete', $order), [
        'actual_yield_quantity' => 95,
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 50],
        ],
        'packaging' => [
            ['id' => $pack->id, 'actual_units' => 20],
        ],
    ]);

    $response->assertRedirect();
    $packagingBatch->refresh();

    expect((float) $packagingBatch->remaining_quantity)->toBe(30.0);

    $this->assertDatabaseHas('inventory_movements', [
        'production_order_id' => $order->id,
        'raw_material_id' => $packagingRawMaterial->id,
        'batch_id' => $packagingBatch->id,
        'quantity' => 20,
        'type' => 'exit',
    ]);
});
