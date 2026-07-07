<?php

declare(strict_types=1);

use App\Actions\Production\CreateProductionOrderAction;
use App\Jobs\RecalculateRawMaterialReferencePrice;
use App\Models\FinishedInventoryMovement;
use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionCost;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\ProductionOrderPackagingPlan;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'email_verified_at' => now(),
        'job_title' => 'Analista de Calidad',
        'signature_path' => 'signatures/test.png',
    ]);
    Role::create(['name' => 'produccion']);
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
        'cif_percentage' => 0,
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
        'code' => 'VAR-001',
        'name' => 'Pintura Test - Galón',
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
        'batch_id' => null,
        'unit_cost' => 5,
        'total_cost' => 250,
    ]);

    // Verificar que se creó el plan de envasado
    $this->assertDatabaseCount('production_order_packaging_plan', 1);
    $this->assertDatabaseHas('production_order_packaging_plan', [
        'product_variant_id' => $variant->id,
        'planned_units' => 20,
    ]);
});

test('it allows order creation for materials that do not track inventory without batches', function () {
    $this->material->update([
        'current_price' => 8,
        'tracks_inventory' => false,
    ]);

    $response = $this->post(route('production-orders.store'), [
        'product_id' => $this->formula->product_id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->factory->id,
        'quantity' => 100,
        'planned_date' => now()->addDay()->toDateString(),
    ]);

    $response->assertRedirect();
    $this->assertDatabaseCount('production_orders', 1);
    $this->assertDatabaseCount('inventory_batches', 0);
    $this->assertDatabaseHas('production_order_details', [
        'raw_material_id' => $this->material->id,
        'planned_quantity' => 50,
        'batch_id' => null,
        'unit_cost' => 8,
        'total_cost' => 400,
    ]);
});

test('store delegates production order creation to the action', function () {
    InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 5,
        'entry_date' => now(),
    ]);

    $variant = ProductVariant::where('product_id', $this->formula->product_id)->firstOrFail();
    $expectedOrder = ProductionOrder::create([
        'order_number' => 'OP-DELEGATE-01',
        'product_id' => $this->formula->product_id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->factory->id,
        'quantity' => 100,
        'status' => 'pending',
        'planned_date' => now()->addDay(),
        'created_by' => $this->user->id,
    ]);

    $action = Mockery::mock(CreateProductionOrderAction::class);
    $action->shouldReceive('execute')
        ->once()
        ->withArgs(function (array $payload, int $userId) use ($variant): bool {
            return $userId === $this->user->id
                && $payload['product_id'] === $this->formula->product_id
                && $payload['formula_id'] === $this->formula->id
                && $payload['warehouse_id'] === $this->factory->id
                && (float) $payload['quantity'] === 100.0
                && $payload['packaging'][0]['product_variant_id'] === $variant->id
                && (float) $payload['packaging'][0]['planned_units'] === 20.0;
        })
        ->andReturn($expectedOrder);

    $this->app->instance(CreateProductionOrderAction::class, $action);

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

    $response->assertRedirect(route('production-orders.show', $expectedOrder));
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

    $this->post(route('production-orders.start', $order));

    $response = $this->post(route('production-orders.complete', $order), [
        'actual_yield_quantity' => 19,
        'viscosity_ku' => 105,
        'grinding_hg' => 7,
        'responsible_name' => 'Operario Juan',
        'density_kg_per_gallon' => 5,
        'quality_responsible_user_id' => $this->user->id,
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

test('it completes order for materials that do not track inventory without consuming batches', function () {
    $this->material->update([
        'current_price' => 8,
        'tracks_inventory' => false,
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-NON-TRACKED',
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
        'batch_id' => null,
        'planned_quantity' => 50,
        'unit_cost' => 8,
        'total_cost' => 400,
    ]);

    $this->post(route('production-orders.start', $order));

    $response = $this->post(route('production-orders.complete', $order), [
        'actual_yield_quantity' => 100,
        'density_kg_per_gallon' => 5,
        'quality_responsible_user_id' => $this->user->id,
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 52],
        ],
        'packaging' => [],
    ]);

    $response->assertRedirect();

    $order->refresh();
    $detail->refresh();

    expect($order->status->value)->toBe('completed');
    expect((float) $detail->unit_cost)->toBe(8.0);
    expect((float) $detail->total_cost)->toBe(416.0);

    $this->assertDatabaseCount('inventory_batches', 0);
    $this->assertDatabaseHas('inventory_movements', [
        'production_order_id' => $order->id,
        'raw_material_id' => $this->material->id,
        'batch_id' => null,
        'type' => 'exit',
        'quantity' => 52,
        'cost_price' => 8,
    ]);
});

test('it prevents completing the same production order twice', function () {
    $batch = InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 5,
        'entry_date' => now(),
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-IDEMPOTENT',
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

    $this->post(route('production-orders.start', $order));

    $this->post(route('production-orders.complete', $order), [
        'actual_yield_quantity' => 100,
        'density_kg_per_gallon' => 5,
        'quality_responsible_user_id' => $this->user->id,
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 50],
        ],
        'packaging' => [],
    ])->assertRedirect();

    $secondResponse = $this->from(route('production-orders.show', $order))
        ->post(route('production-orders.complete', $order), [
            'actual_yield_quantity' => 100,
            'density_kg_per_gallon' => 5,
            'quality_responsible_user_id' => $this->user->id,
            'ingredients' => [
                ['id' => $detail->id, 'actual_quantity' => 50],
            ],
            'packaging' => [],
        ]);

    $secondResponse->assertForbidden();
    expect(InventoryMovement::where('production_order_id', $order->id)->count())->toBe(1);
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

    $this->post(route('production-orders.start', $order));

    $response = $this->post(route('production-orders.complete', $order), [
        'actual_yield_quantity' => 98,
        'density_kg_per_gallon' => 5,
        'quality_responsible_user_id' => $this->user->id,
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 50],
        ],
    ]);

    $response->assertRedirect();
    $order->refresh();

    expect($order->status->value)->toBe('completed');
    $this->assertDatabaseCount('finished_inventory_movements', 0);
});

test('it rejects completion when actual yield does not match packaging equivalent beyond tolerance', function () {
    $batch = InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 5,
        'entry_date' => now(),
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-YIELD-VALID',
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
    $variant->update(['presentation_value' => 5]);

    $pack = ProductionOrderPackagingPlan::create([
        'production_order_id' => $order->id,
        'product_variant_id' => $variant->id,
        'planned_units' => 2,
    ]);

    $this->post(route('production-orders.start', $order));

    $response = $this->from(route('production-orders.show', $order))
        ->post(route('production-orders.complete', $order), [
            'actual_yield_quantity' => 9.98,
            'density_kg_per_gallon' => 5,
            'quality_responsible_user_id' => $this->user->id,
            'ingredients' => [
                ['id' => $detail->id, 'actual_quantity' => 50],
            ],
            'packaging' => [
                ['id' => $pack->id, 'actual_units' => 2],
            ],
        ]);

    $response->assertRedirect(route('production-orders.show', $order));
    $response->assertSessionHasErrors('actual_yield_quantity');
    $order->refresh();
    expect($order->status->value)->toBe('in_progress');
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
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Production/Orders/Show')
    );
    $response->assertSee('OP-VIEW-0001');
    $response->assertSee('Pintura Test');
    $response->assertSee('packaging_plans');
});

test('it creates separate finished inventory records per variant when packaging has multiple variants', function () {
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
        'code' => 'VAR-002',
        'name' => 'Pintura Test - Cuarto',
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

    $this->post(route('production-orders.start', $order));

    $response = $this->post(route('production-orders.complete', $order), [
        'actual_yield_quantity' => 20,
        'density_kg_per_gallon' => 5,
        'quality_responsible_user_id' => $this->user->id,
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 50],
        ],
        'packaging' => [
            ['id' => $firstPack->id, 'actual_units' => 10],
            ['id' => $secondPack->id, 'actual_units' => 10],
        ],
    ]);

    $response->assertRedirect();

    // Each variant gets its own finished_inventories record (trazabilidad por variante)
    $this->assertDatabaseCount('finished_inventories', 2);
    $this->assertDatabaseHas('finished_inventories', [
        'product_id' => $order->product_id,
        'product_variant_id' => $firstVariant->id,
        'warehouse_id' => $order->warehouse_id,
        'quantity' => 10,
    ]);
    $this->assertDatabaseHas('finished_inventories', [
        'product_id' => $order->product_id,
        'product_variant_id' => $secondVariant->id,
        'warehouse_id' => $order->warehouse_id,
        'quantity' => 10,
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

    $this->post(route('production-orders.start', $order));

    $response = $this->post(route('production-orders.complete', $order), [
        'actual_yield_quantity' => 150,
        'density_kg_per_gallon' => 5,
        'quality_responsible_user_id' => $this->user->id,
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 150],
        ],
        'packaging' => [],
    ]);

    $response->assertRedirect();

    $oldestBatch->refresh();
    $middleBatch->refresh();
    $detail->refresh();

    expect((float) $oldestBatch->remaining_quantity)->toBe(0.0);
    expect((float) $middleBatch->remaining_quantity)->toBe(50.0);
    expect((float) $detail->total_cost)->toBe(800.0);
    expect(round((float) $detail->unit_cost, 4))->toBe(5.3333);

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

    $this->post(route('production-orders.start', $order));

    $response = $this->post(route('production-orders.complete', $order), [
        'actual_yield_quantity' => 20,
        'density_kg_per_gallon' => 5,
        'quality_responsible_user_id' => $this->user->id,
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

test('it calculates cost_price correctly for single variant with packaging', function () {
    Queue::fake();

    $batch = InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 5,
        'entry_date' => now(),
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-COST-001',
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
    $variant->update(['presentation_value' => 1]); // 1 galón
    Product::where('id', $order->product_id)->update([
        'cif_percentage' => 20,
        'price_threshold' => 0,
    ]);

    $pack = ProductionOrderPackagingPlan::create([
        'production_order_id' => $order->id,
        'product_variant_id' => $variant->id,
        'planned_units' => 20,
    ]);

    $this->post(route('production-orders.start', $order));

    $response = $this->post(route('production-orders.complete', $order), [
        'actual_yield_quantity' => 20,
        'density_kg_per_gallon' => 5,
        'quality_responsible_user_id' => $this->user->id,
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 50],
        ],
        'packaging' => [
            ['id' => $pack->id, 'actual_units' => 20],
        ],
    ]);

    $response->assertRedirect();

    // Verify cost_price was calculated and stored
    $movement = FinishedInventoryMovement::where('production_order_id', $order->id)
        ->where('product_variant_id', $variant->id)
        ->first();

    expect($movement)->not->toBeNull();
    expect((float) $movement->cost_price)->toBe(12.5); // (50*5)/20 = 12.5
    expect((float) $movement->quantity)->toBe(20.0);

    Queue::assertPushed(RecalculateRawMaterialReferencePrice::class, function ($job) {
        return $job->rawMaterialId === $this->material->id;
    });
});

test('it distributes bulk cost across multiple variants by presentation_value', function () {
    $batch = InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 5,
        'entry_date' => now(),
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-MULTI-COST',
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

    // Create two variants with different presentation_values
    $variant1 = ProductVariant::where('product_id', $order->product_id)->first();
    $variant1->update(['presentation_value' => 1, 'code' => 'VAR-GALON']); // Galón = 1

    $variant2 = ProductVariant::create([
        'product_id' => $order->product_id,
        'code' => 'VAR-BIDON',
        'name' => 'Pintura Test - Bidón 5G',
        'unit_of_measure_id' => $variant1->unit_of_measure_id,
        'presentation_value' => 5,
        'presentation_label' => 'Bidon 5 Galones',
        'is_active' => true,
    ]);

    // Create packaging plans: 20 gallons + 2 bidons (10 gallons) = 30 gallons total
    $pack1 = ProductionOrderPackagingPlan::create([
        'production_order_id' => $order->id,
        'product_variant_id' => $variant1->id,
        'planned_units' => 20,
    ]);

    $pack2 = ProductionOrderPackagingPlan::create([
        'production_order_id' => $order->id,
        'product_variant_id' => $variant2->id,
        'planned_units' => 2,
    ]);

    $this->post(route('production-orders.start', $order));

    $response = $this->post(route('production-orders.complete', $order), [
        'actual_yield_quantity' => 30,
        'density_kg_per_gallon' => 5,
        'quality_responsible_user_id' => $this->user->id,
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 50],
        ],
        'packaging' => [
            ['id' => $pack1->id, 'actual_units' => 20],
            ['id' => $pack2->id, 'actual_units' => 2],
        ],
    ]);

    $response->assertRedirect();

    // Total bulk cost: 50 * 5 = 250
    // Total units: 20 + (2*5) = 30 gallons equivalent
    // Cost per gallon: 250 / 30 = 8.333...
    // Galón cost: 8.333... * 1 = 8.333...
    // Bidon cost: 8.333... * 5 = 41.666...

    $movement1 = FinishedInventoryMovement::where('production_order_id', $order->id)
        ->where('product_variant_id', $variant1->id)
        ->first();

    $movement2 = FinishedInventoryMovement::where('production_order_id', $order->id)
        ->where('product_variant_id', $variant2->id)
        ->first();

    expect($movement1)->not->toBeNull();
    expect($movement2)->not->toBeNull();

    expect(round((float) $movement1->cost_price, 2))->toBe(8.33); // Galón
    expect(round((float) $movement2->cost_price, 2))->toBe(41.67); // Bidon
});

test('it includes packaging material cost in cost_price', function () {
    $ingredientBatch = InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 5,
        'entry_date' => now(),
    ]);

    $packagingMaterial = RawMaterial::create([
        'code' => 'ENV-GALON',
        'unit_of_measure_id' => UnitOfMeasure::create(['code' => 'UN', 'name' => 'Unidad', 'symbol' => 'UN'])->id,
        'current_price' => 500,
    ]);

    $packagingBatch = InventoryBatch::create([
        'raw_material_id' => $packagingMaterial->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 500,
        'entry_date' => now(),
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-PKG-COST',
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
        'batch_id' => $ingredientBatch->id,
        'planned_quantity' => 50,
        'unit_cost' => 5,
        'total_cost' => 250,
    ]);

    $variant = ProductVariant::where('product_id', $order->product_id)->first();
    $variant->update([
        'presentation_value' => 1,
        'package_raw_material_id' => $packagingMaterial->id,
    ]);

    $pack = ProductionOrderPackagingPlan::create([
        'production_order_id' => $order->id,
        'product_variant_id' => $variant->id,
        'planned_units' => 20,
    ]);

    $this->post(route('production-orders.start', $order));

    $response = $this->post(route('production-orders.complete', $order), [
        'actual_yield_quantity' => 20,
        'density_kg_per_gallon' => 5,
        'quality_responsible_user_id' => $this->user->id,
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 50],
        ],
        'packaging' => [
            ['id' => $pack->id, 'actual_units' => 20],
        ],
    ]);

    $response->assertRedirect();

    // Total bulk cost: 250, Total units: 20, Cost per unit: 12.5
    // Packaging cost: 500 (unit_price from batch)
    // Total cost_price: 12.5 + 500 = 512.5
    $movement = FinishedInventoryMovement::where('production_order_id', $order->id)
        ->where('product_variant_id', $variant->id)
        ->first();

    expect($movement)->not->toBeNull();
    expect((float) $movement->cost_price)->toBe(512.5); // 12.5 (bulk) + 500 (packaging)
});

test('it creates production_costs record for historical tracking', function () {
    $batch = InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 5,
        'entry_date' => now(),
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-HIST-001',
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

    $this->post(route('production-orders.start', $order));

    $response = $this->post(route('production-orders.complete', $order), [
        'actual_yield_quantity' => 20,
        'density_kg_per_gallon' => 5,
        'quality_responsible_user_id' => $this->user->id,
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 50],
        ],
        'packaging' => [
            ['id' => $pack->id, 'actual_units' => 20],
        ],
    ]);

    $response->assertRedirect();

    // Verify ProductionCost historical record was created for this order
    $this->assertDatabaseHas('production_costs', [
        'production_order_id' => $order->id,
        'product_id' => $order->product_id,
        'formula_id' => $order->formula_id,
        'cost' => 250, // Total bulk cost: 50 * 5
        'unit_cost' => 12.5, // 250 / 20
    ]);
});

test('it updates existing production_cost record for the same order instead of failing unique constraint', function () {
    $batch = InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 5,
        'entry_date' => now(),
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-HIST-UPD',
        'product_id' => $this->formula->product_id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->factory->id,
        'quantity' => 100,
        'status' => 'pending',
        'planned_date' => now(),
        'created_by' => $this->user->id,
    ]);

    ProductionCost::create([
        'product_id' => $order->product_id,
        'formula_id' => $order->formula_id,
        'production_order_id' => $order->id,
        'cost' => 1,
        'unit_cost' => 1,
        'calculated_at' => now()->subDay(),
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

    $this->post(route('production-orders.start', $order));

    $response = $this->post(route('production-orders.complete', $order), [
        'actual_yield_quantity' => 20,
        'density_kg_per_gallon' => 5,
        'quality_responsible_user_id' => $this->user->id,
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 50],
        ],
        'packaging' => [
            ['id' => $pack->id, 'actual_units' => 20],
        ],
    ]);

    $response->assertRedirect();
    expect(ProductionCost::where('production_order_id', $order->id)->count())->toBe(1);

    $this->assertDatabaseHas('production_costs', [
        'production_order_id' => $order->id,
        'cost' => 250,
        'unit_cost' => 12.5,
    ]);
});

test('it keeps production_costs history for multiple orders with the same formula', function () {
    Queue::fake();

    $batch = InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 300,
        'remaining_quantity' => 300,
        'unit_price' => 5,
        'entry_date' => now(),
    ]);

    $firstOrder = ProductionOrder::create([
        'order_number' => 'OP-HIST-A',
        'product_id' => $this->formula->product_id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->factory->id,
        'quantity' => 100,
        'status' => 'pending',
        'planned_date' => now(),
        'created_by' => $this->user->id,
    ]);

    $firstDetail = ProductionOrderDetail::create([
        'production_order_id' => $firstOrder->id,
        'raw_material_id' => $this->material->id,
        'batch_id' => $batch->id,
        'planned_quantity' => 50,
        'unit_cost' => 5,
        'total_cost' => 250,
    ]);

    $this->post(route('production-orders.start', $firstOrder));

    $this->post(route('production-orders.complete', $firstOrder), [
        'density_kg_per_gallon' => 5,
        'quality_responsible_user_id' => $this->user->id,
        'ingredients' => [
            ['id' => $firstDetail->id, 'actual_quantity' => 50],
        ],
        'packaging' => [],
    ])->assertRedirect();

    $secondOrder = ProductionOrder::create([
        'order_number' => 'OP-HIST-B',
        'product_id' => $this->formula->product_id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->factory->id,
        'quantity' => 120,
        'status' => 'pending',
        'planned_date' => now(),
        'created_by' => $this->user->id,
    ]);

    $secondDetail = ProductionOrderDetail::create([
        'production_order_id' => $secondOrder->id,
        'raw_material_id' => $this->material->id,
        'batch_id' => $batch->id,
        'planned_quantity' => 60,
        'unit_cost' => 5,
        'total_cost' => 300,
    ]);

    $this->post(route('production-orders.start', $secondOrder));

    $this->post(route('production-orders.complete', $secondOrder), [
        'density_kg_per_gallon' => 5,
        'quality_responsible_user_id' => $this->user->id,
        'ingredients' => [
            ['id' => $secondDetail->id, 'actual_quantity' => 60],
        ],
        'packaging' => [],
    ])->assertRedirect();

    expect(ProductionCost::count())->toBe(2);
    $this->assertDatabaseHas('production_costs', [
        'production_order_id' => $firstOrder->id,
        'cost' => 250,
    ]);
    $this->assertDatabaseHas('production_costs', [
        'production_order_id' => $secondOrder->id,
        'cost' => 300,
    ]);

    Queue::assertPushed(RecalculateRawMaterialReferencePrice::class);
});

test('it sets unit_cost and total_cost to zero when actual quantity is zero', function () {
    $batch = InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 5,
        'entry_date' => now(),
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-ZERO-001',
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

    $this->post(route('production-orders.start', $order));

    $this->post(route('production-orders.complete', $order), [
        'density_kg_per_gallon' => 5,
        'quality_responsible_user_id' => $this->user->id,
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 0],
        ],
        'packaging' => [],
    ])->assertRedirect();

    $detail->refresh();
    expect((float) $detail->unit_cost)->toBe(0.0);
    expect((float) $detail->total_cost)->toBe(0.0);
});

test('it previews fifo costs from backend endpoint using multiple batches', function () {
    InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 50,
        'remaining_quantity' => 50,
        'unit_price' => 5,
        'entry_date' => now()->subDay(),
    ]);

    InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 50,
        'remaining_quantity' => 50,
        'unit_price' => 7,
        'entry_date' => now(),
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-PREVIEW-001',
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
        'batch_id' => null,
        'planned_quantity' => 50,
        'unit_cost' => 5,
        'total_cost' => 250,
    ]);

    $response = $this->postJson(route('production-orders.preview-costs', $order), [
        'density_kg_per_gallon' => 5,
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 60],
        ],
        'packaging' => [],
    ]);

    $response->assertOk();
    expect(round((float) $response->json('ingredients.0.unit_cost'), 4))->toBe(5.3333);
    // Float precision tolerance: total_cost should be ~320.0
    expect(abs((float) $response->json('ingredients.0.total_cost') - 320.0))->toBeLessThan(0.01);
    expect(abs((float) $response->json('total_bulk_cost') - 320.0))->toBeLessThan(0.01);
});

test('it rejects completion when ingredient detail ids are duplicated', function () {
    $batch = InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 5,
        'entry_date' => now(),
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-DUP-ING',
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

    $this->post(route('production-orders.start', $order));

    $response = $this->from(route('production-orders.show', $order))
        ->post(route('production-orders.complete', $order), [
            'actual_yield_quantity' => 100,
            'density_kg_per_gallon' => 5,
            'quality_responsible_user_id' => $this->user->id,
            'ingredients' => [
                ['id' => $detail->id, 'actual_quantity' => 25],
                ['id' => $detail->id, 'actual_quantity' => 25],
            ],
            'packaging' => [],
        ]);

    $response->assertRedirect(route('production-orders.show', $order));
    $response->assertSessionHasErrors('ingredients.1.id');
});

test('it rejects preview when packaging ids are duplicated', function () {
    InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 5,
        'entry_date' => now(),
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-DUP-PREVIEW',
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
        'batch_id' => null,
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

    $response = $this->postJson(route('production-orders.preview-costs', $order), [
        'density_kg_per_gallon' => 5,
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 50],
        ],
        'packaging' => [
            ['id' => $pack->id, 'actual_units' => 10],
            ['id' => $pack->id, 'actual_units' => 10],
        ],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('packaging.1.id');
});

test('it generates sequential lot numbers starting from config value', function () {
    config(['production.lot_start_number' => 1620]);

    InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 5,
        'entry_date' => now(),
    ]);

    $response = $this->post(route('production-orders.store'), [
        'product_id' => $this->formula->product_id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->factory->id,
        'quantity' => 10,
        'planned_date' => now()->addDay()->toDateString(),
    ]);

    $response->assertRedirect();
    $firstOrder = ProductionOrder::orderBy('id', 'desc')->first();
    expect($firstOrder->lot_number)->toBe(1620);

    $response2 = $this->post(route('production-orders.store'), [
        'product_id' => $this->formula->product_id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->factory->id,
        'quantity' => 10,
        'planned_date' => now()->addDay()->toDateString(),
    ]);

    $response2->assertRedirect();
    $secondOrder = ProductionOrder::orderBy('id', 'desc')->first();
    expect($secondOrder->lot_number)->toBe(1621);
});
