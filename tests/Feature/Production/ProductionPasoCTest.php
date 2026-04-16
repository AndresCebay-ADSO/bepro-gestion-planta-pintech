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
use Spatie\Permission\Models\Role;
use App\Services\ProductionOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->assignRole(Role::create(['name' => 'admin']));
    $this->actingAs($this->user);

    // Planta Cali (ID 1 es forzado en el servicio, así que lo creamos así)
    $this->factory = Warehouse::create([
        'id' => 1,
        'name' => 'Planta Cali',
        'city' => 'Cali',
        'type' => 'factory',
        'is_active' => true
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

    $response = $this->post(route('production-orders.store'), [
        'product_id' => $this->formula->product_id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->factory->id,
        'quantity' => 100,
        'planned_date' => now()->addDay()->toDateString(),
    ]);

    $response->assertRedirect();
    $this->assertDatabaseCount('production_orders', 1);
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
            ['id' => $detail->id, 'actual_quantity' => 52]
        ],
        'packaging' => [
            ['id' => $pack->id, 'actual_units' => 19]
        ],
    ]);

    $response->assertRedirect();

    $order->refresh();
    expect($order->status->value)->toBe('completed');
    expect((float)$order->viscosity_ku)->toBe(105.0);
    
    $batch->refresh();
    expect((float)$batch->remaining_quantity)->toBe(48.0);
    
    $this->assertDatabaseHas('inventory_movements', [
        'production_order_id' => $order->id,
        'type' => 'exit',
        'quantity' => 52
    ]);

    $this->assertDatabaseHas('finished_inventories', [
        'product_id' => $order->product_id,
        'product_variant_id' => $variant->id,
        'quantity' => 19
    ]);
});
