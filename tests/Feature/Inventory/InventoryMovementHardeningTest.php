<?php

declare(strict_types=1);

use App\Models\Formula;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $adminRole = Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'produccion']);

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole($adminRole);
    $this->actingAs($this->admin);

    $uom = UnitOfMeasure::create([
        'code' => 'KG',
        'name' => 'Kilogramo',
        'symbol' => 'kg',
        'is_active' => true,
    ]);

    $this->rawMaterial = RawMaterial::create([
        'code' => 'RM-HARD-001',
        'unit_of_measure_id' => $uom->id,
        'current_price' => 10,
        'minimum_stock' => 0,
        'alert_days_before_expiry' => 30,
        'is_active' => true,
    ]);

    $this->warehouseA = Warehouse::create([
        'name' => 'Bodega A',
        'city' => 'Cali',
        'type' => 'factory',
        'is_active' => true,
    ]);

    $this->warehouseB = Warehouse::create([
        'name' => 'Bodega B',
        'city' => 'Bogota',
        'type' => 'storage',
        'is_active' => true,
    ]);
});

test('it rejects movements when batch belongs to another warehouse', function () {
    $batch = InventoryBatch::create([
        'raw_material_id' => $this->rawMaterial->id,
        'warehouse_id' => $this->warehouseA->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 12,
        'entry_date' => now()->toDateString(),
    ]);

    $response = $this->from(route('inventory-movements.index'))
        ->post(route('inventory-movements.store'), [
            'raw_material_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouseB->id,
            'batch_id' => $batch->id,
            'type' => 'exit',
            'quantity' => 10,
            'movement_date' => now()->toDateString(),
        ]);

    $response->assertRedirect(route('inventory-movements.index'));
    $response->assertSessionHasErrors('batch_id');
    expect(InventoryMovement::count())->toBe(0);
});

test('it prevents editing movements linked to production orders', function () {
    $batch = InventoryBatch::create([
        'raw_material_id' => $this->rawMaterial->id,
        'warehouse_id' => $this->warehouseA->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 8,
        'entry_date' => now()->toDateString(),
    ]);

    $category = ProductCategory::create(['name' => 'General']);
    $product = Product::create([
        'code' => 'P-HARD-001',
        'name' => 'Producto Hardening',
        'category_id' => $category->id,
        'unit_of_measure_id' => $this->rawMaterial->unit_of_measure_id,
        'current_cost' => 1,
        'current_price' => 1,
    ]);
    $formula = Formula::create([
        'product_id' => $product->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $this->admin->id,
    ]);
    $order = ProductionOrder::create([
        'order_number' => 'OP-HARD-001',
        'product_id' => $product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $this->warehouseA->id,
        'quantity' => 10,
        'status' => 'pending',
        'planned_date' => now()->toDateString(),
        'created_by' => $this->admin->id,
    ]);

    $movement = InventoryMovement::create([
        'raw_material_id' => $this->rawMaterial->id,
        'warehouse_id' => $this->warehouseA->id,
        'batch_id' => $batch->id,
        'production_order_id' => $order->id,
        'type' => 'exit',
        'quantity' => 5,
        'cost_price' => 8,
        'movement_date' => now()->toDateString(),
        'created_by' => $this->admin->id,
    ]);

    $response = $this->from(route('inventory-movements.index'))
        ->put(route('inventory-movements.update', $movement), [
            'raw_material_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouseA->id,
            'batch_id' => $batch->id,
            'type' => 'exit',
            'quantity' => 4,
            'movement_date' => now()->toDateString(),
        ]);

    $response->assertForbidden();
});

test('it prevents deleting movements linked to production orders', function () {
    $batch = InventoryBatch::create([
        'raw_material_id' => $this->rawMaterial->id,
        'warehouse_id' => $this->warehouseA->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 8,
        'entry_date' => now()->toDateString(),
    ]);

    $category = ProductCategory::create(['name' => 'General']);
    $product = Product::create([
        'code' => 'P-HARD-DEL',
        'name' => 'Producto Hardening Delete',
        'category_id' => $category->id,
        'unit_of_measure_id' => $this->rawMaterial->unit_of_measure_id,
        'current_cost' => 1,
        'current_price' => 1,
    ]);
    $formula = Formula::create([
        'product_id' => $product->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $this->admin->id,
    ]);
    $order = ProductionOrder::create([
        'order_number' => 'OP-HARD-DEL',
        'product_id' => $product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $this->warehouseA->id,
        'quantity' => 10,
        'status' => 'pending',
        'planned_date' => now()->toDateString(),
        'created_by' => $this->admin->id,
    ]);

    $movement = InventoryMovement::create([
        'raw_material_id' => $this->rawMaterial->id,
        'warehouse_id' => $this->warehouseA->id,
        'batch_id' => $batch->id,
        'production_order_id' => $order->id,
        'type' => 'exit',
        'quantity' => 5,
        'cost_price' => 8,
        'movement_date' => now()->toDateString(),
        'created_by' => $this->admin->id,
    ]);

    $response = $this->from(route('inventory-movements.index'))
        ->delete(route('inventory-movements.destroy', $movement));

    $response->assertForbidden();
    $this->assertDatabaseHas('inventory_movements', ['id' => $movement->id]);
});

test('it updates batch quantities and cost when editing an entry movement', function () {
    $batch = InventoryBatch::create([
        'raw_material_id' => $this->rawMaterial->id,
        'warehouse_id' => $this->warehouseA->id,
        'initial_quantity' => 10,
        'remaining_quantity' => 10,
        'unit_price' => 10,
        'entry_date' => now()->subDay()->toDateString(),
    ]);

    $movement = InventoryMovement::create([
        'raw_material_id' => $this->rawMaterial->id,
        'warehouse_id' => $this->warehouseA->id,
        'batch_id' => $batch->id,
        'type' => 'entry',
        'quantity' => 10,
        'cost_price' => 10,
        'movement_date' => now()->subDay()->toDateString(),
        'created_by' => $this->admin->id,
    ]);

    $response = $this->from(route('inventory-movements.index'))
        ->put(route('inventory-movements.update', $movement), [
            'raw_material_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouseA->id,
            'batch_id' => $batch->id,
            'type' => 'entry',
            'quantity' => 15,
            'cost_price' => 25,
            'movement_date' => now()->toDateString(),
        ]);

    $response->assertRedirect(route('inventory-movements.index'));

    $batch->refresh();
    $this->rawMaterial->refresh();
    expect((float) $batch->initial_quantity)->toBe(15.0);
    expect((float) $batch->remaining_quantity)->toBe(15.0);
    expect((float) $batch->unit_price)->toBe(25.0);
    expect((float) $this->rawMaterial->current_price)->toBe(25.0);
});

test('it removes orphaned batches when deleting their only entry movement', function () {
    $movement = InventoryMovement::create([
        'raw_material_id' => $this->rawMaterial->id,
        'warehouse_id' => $this->warehouseA->id,
        'type' => 'entry',
        'quantity' => 12,
        'cost_price' => 18,
        'movement_date' => now()->toDateString(),
        'created_by' => $this->admin->id,
        'batch_id' => InventoryBatch::create([
            'raw_material_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouseA->id,
            'initial_quantity' => 12,
            'remaining_quantity' => 12,
            'unit_price' => 18,
            'entry_date' => now()->toDateString(),
        ])->id,
    ]);

    $batchId = (int) $movement->batch_id;

    $response = $this->from(route('inventory-movements.index'))
        ->delete(route('inventory-movements.destroy', $movement));

    $response->assertRedirect(route('inventory-movements.index'));
    $this->assertDatabaseMissing('inventory_movements', ['id' => $movement->id]);
    $this->assertDatabaseMissing('inventory_batches', ['id' => $batchId]);
});

test('it only exposes available batches for the current warehouse in inventory movements screens', function () {
    $batchInCurrentWarehouse = InventoryBatch::create([
        'raw_material_id' => $this->rawMaterial->id,
        'warehouse_id' => $this->warehouseA->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 25,
        'unit_price' => 10,
        'entry_date' => now()->toDateString(),
    ]);

    InventoryBatch::create([
        'raw_material_id' => $this->rawMaterial->id,
        'warehouse_id' => $this->warehouseA->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 0,
        'unit_price' => 11,
        'entry_date' => now()->toDateString(),
    ]);

    InventoryBatch::create([
        'raw_material_id' => $this->rawMaterial->id,
        'warehouse_id' => $this->warehouseB->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 50,
        'unit_price' => 12,
        'entry_date' => now()->toDateString(),
    ]);

    $response = $this->withSession(['current_warehouse_id' => $this->warehouseA->id])
        ->actingAs($this->admin)
        ->get(route('inventory-movements.index', ['open' => 'exit']));

    $response->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Inventory/Movements/Index')
            ->where('currentWarehouseId', $this->warehouseA->id)
        );

    $movement = InventoryMovement::create([
        'raw_material_id' => $this->rawMaterial->id,
        'warehouse_id' => $this->warehouseA->id,
        'batch_id' => $batchInCurrentWarehouse->id,
        'type' => 'entry',
        'quantity' => 5,
        'cost_price' => 10,
        'movement_date' => now()->toDateString(),
        'created_by' => $this->admin->id,
    ]);

    $editResponse = $this->actingAs($this->admin)
        ->get(route('inventory-movements.edit', $movement));

    $editResponse->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Inventory/Movements/Edit')
            ->has('batches', 1)
            ->where('batches.0.id', $batchInCurrentWarehouse->id)
            ->where('batches.0.warehouse_id', $this->warehouseA->id)
        );
});
