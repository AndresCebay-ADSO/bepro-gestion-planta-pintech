<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Jobs\RecalculateRawMaterialReferencePrice;
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
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('admin');
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

test('it dispatches reference price recalculation after storing a movement', function () {
    Queue::fake();

    $response = $this->from(route('inventory-movements.index'))
        ->post(route('inventory-movements.store'), [
            'raw_material_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouseA->id,
            'batch_id' => null,
            'type' => 'entry',
            'quantity' => 10,
            'cost_price' => 12,
            'lot_number' => 'LOT-JOB-001',
            'movement_date' => now()->toDateString(),
        ]);

    $response->assertRedirect(route('inventory-movements.index'));
    $response->assertSessionHasNoErrors();

    Queue::assertPushed(
        RecalculateRawMaterialReferencePrice::class,
        fn (RecalculateRawMaterialReferencePrice $job): bool => $job->rawMaterialId === (int) $this->rawMaterial->id
    );
});

test('it requires a lot number when an entry creates a new batch', function () {
    $response = $this->from(route('inventory-movements.index'))
        ->post(route('inventory-movements.store'), [
            'raw_material_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouseA->id,
            'batch_id' => null,
            'type' => 'entry',
            'quantity' => 10,
            'cost_price' => 12,
            'movement_date' => now()->toDateString(),
        ]);

    $response->assertRedirect(route('inventory-movements.index'));
    $response->assertSessionHasErrors('lot_number');
    expect(InventoryMovement::count())->toBe(0);
    expect(InventoryBatch::count())->toBe(0);
});

test('it rejects adding stock with a different cost to an existing batch', function () {
    $batch = InventoryBatch::create([
        'raw_material_id' => $this->rawMaterial->id,
        'warehouse_id' => $this->warehouseA->id,
        'initial_quantity' => 10,
        'remaining_quantity' => 10,
        'unit_price' => 10,
        'entry_date' => now()->toDateString(),
    ]);

    $response = $this->from(route('inventory-movements.index'))
        ->post(route('inventory-movements.store'), [
            'raw_material_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouseA->id,
            'batch_id' => $batch->id,
            'type' => 'entry',
            'quantity' => 30,
            'cost_price' => 30,
            'movement_date' => now()->toDateString(),
        ]);

    $response->assertRedirect(route('inventory-movements.index'));
    $response->assertSessionHasErrors('cost_price');

    $batch->refresh();
    expect((float) $batch->initial_quantity)->toBe(10.0);
    expect((float) $batch->remaining_quantity)->toBe(10.0);
    expect((float) $batch->unit_price)->toBe(10.0);
});

test('it allows adding stock to an existing batch when cost matches', function () {
    $batch = InventoryBatch::create([
        'raw_material_id' => $this->rawMaterial->id,
        'warehouse_id' => $this->warehouseA->id,
        'initial_quantity' => 10,
        'remaining_quantity' => 10,
        'unit_price' => 10,
        'entry_date' => now()->toDateString(),
    ]);

    $response = $this->from(route('inventory-movements.index'))
        ->post(route('inventory-movements.store'), [
            'raw_material_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouseA->id,
            'batch_id' => $batch->id,
            'type' => 'entry',
            'quantity' => 30,
            'cost_price' => 10,
            'movement_date' => now()->toDateString(),
        ]);

    $response->assertRedirect(route('inventory-movements.index'));
    $response->assertSessionHasNoErrors();

    $batch->refresh();
    expect((float) $batch->initial_quantity)->toBe(40.0);
    expect((float) $batch->remaining_quantity)->toBe(40.0);
    expect((float) $batch->unit_price)->toBe(10.0);
});

test('it ignores submitted cost price for exits and stores the batch cost', function () {
    $batch = InventoryBatch::create([
        'raw_material_id' => $this->rawMaterial->id,
        'warehouse_id' => $this->warehouseA->id,
        'initial_quantity' => 20,
        'remaining_quantity' => 20,
        'unit_price' => 12,
        'entry_date' => now()->toDateString(),
    ]);

    $response = $this->from(route('inventory-movements.index'))
        ->post(route('inventory-movements.store'), [
            'raw_material_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouseA->id,
            'batch_id' => $batch->id,
            'type' => 'exit',
            'quantity' => 5,
            'cost_price' => 999,
            'movement_date' => now()->toDateString(),
        ]);

    $response->assertRedirect(route('inventory-movements.index'));
    $response->assertSessionHasNoErrors();

    $movement = InventoryMovement::query()->latest('id')->firstOrFail();
    $batch->refresh();
    expect((float) $movement->cost_price)->toBe(12.0);
    expect((float) $batch->remaining_quantity)->toBe(15.0);
});

test('it rejects manual movements linked to production orders', function () {
    $category = ProductCategory::create(['name' => 'General']);
    $product = Product::create([
        'code' => 'P-HARD-MANUAL-LINK',
        'name' => 'Producto Manual Link',
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
        'order_number' => 'OP-HARD-MANUAL-LINK',
        'product_id' => $product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $this->warehouseA->id,
        'quantity' => 10,
        'status' => 'pending',
        'planned_date' => now()->toDateString(),
        'created_by' => $this->admin->id,
    ]);

    $response = $this->from(route('inventory-movements.index'))
        ->post(route('inventory-movements.store'), [
            'raw_material_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouseA->id,
            'batch_id' => null,
            'production_order_id' => $order->id,
            'type' => 'entry',
            'quantity' => 5,
            'cost_price' => 10,
            'lot_number' => 'LOT-MANUAL-LINK',
            'movement_date' => now()->toDateString(),
        ]);

    $response->assertRedirect(route('inventory-movements.index'));
    $response->assertSessionHasErrors('production_order_id');
    expect(InventoryMovement::count())->toBe(0);
});

test('it resolves the current warehouse in the inventory movements screen', function () {
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

});

test('it does not expose edit, update or delete routes for raw material movements', function () {
    $movement = InventoryMovement::create([
        'raw_material_id' => $this->rawMaterial->id,
        'warehouse_id' => $this->warehouseA->id,
        'type' => 'entry',
        'quantity' => 5,
        'cost_price' => 10,
        'movement_date' => now()->toDateString(),
        'created_by' => $this->admin->id,
    ]);

    expect(Route::has('inventory-movements.edit'))->toBeFalse()
        ->and(Route::has('inventory-movements.update'))->toBeFalse()
        ->and(Route::has('inventory-movements.destroy'))->toBeFalse();

    $this->actingAs($this->admin)
        ->get("/inventory-movements/{$movement->id}/edit")
        ->assertNotFound();

    $this->actingAs($this->admin)
        ->put("/inventory-movements/{$movement->id}", [])
        ->assertMethodNotAllowed();

    $this->actingAs($this->admin)
        ->delete("/inventory-movements/{$movement->id}")
        ->assertMethodNotAllowed();
});

test('it denies update and delete abilities even for super-admin', function () {
    $movement = InventoryMovement::create([
        'raw_material_id' => $this->rawMaterial->id,
        'warehouse_id' => $this->warehouseA->id,
        'type' => 'entry',
        'quantity' => 5,
        'cost_price' => 10,
        'movement_date' => now()->toDateString(),
        'created_by' => $this->admin->id,
    ]);

    $superAdmin = userWithRole(SystemRole::SuperAdmin);

    expect(Gate::forUser($superAdmin)->allows('update', $movement))->toBeFalse()
        ->and(Gate::forUser($superAdmin)->allows('delete', $movement))->toBeFalse();
});
