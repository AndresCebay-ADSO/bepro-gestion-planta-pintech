<?php

use App\Enums\FinishedInventoryMovementReason;
use App\Enums\InventoryMovementType;
use App\Models\FinishedInventoryMovement;
use App\Models\FinishedProductBatch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin']);
});

function createFinishedMovementFixture(string $code = 'PROD-RET'): array
{
    $admin = User::factory()->create()->assignRole('admin');
    $unit = UnitOfMeasure::factory()->create();
    $category = ProductCategory::create(['name' => "Cat {$code}"]);

    $product = Product::create([
        'code' => $code,
        'name' => "Producto {$code}",
        'brand' => 'BEPRO',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    $batch = FinishedProductBatch::create([
        'product_id' => $product->id,
        'initial_quantity' => '20',
        'entry_date' => now(),
    ]);

    return [$admin, $product, $batch];
}

function registerFinishedMovement(
    User $admin,
    FinishedProductBatch $batch,
    Warehouse $warehouse,
    string $type,
    string $reason,
    string $quantity,
): void {
    actingAs($admin)
        ->post(route('finished-inventory-movements.store'), [
            'finished_product_batch_id' => $batch->id,
            'warehouse_id' => $warehouse->id,
            'type' => $type,
            'reason' => $reason,
            'quantity' => $quantity,
            'movement_date' => now()->toDateString(),
        ])
        ->assertRedirect();
}

it('does not expose edit update or delete routes for finished inventory movements', function () {
    [$admin, $product, $batch] = createFinishedMovementFixture('PROD-IMMUTABLE');
    $warehouse = Warehouse::factory()->create();

    $movement = FinishedInventoryMovement::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'finished_product_batch_id' => $batch->id,
        'type' => InventoryMovementType::Entry,
        'reason' => FinishedInventoryMovementReason::Adjustment,
        'quantity' => '10',
        'movement_date' => now(),
        'created_by' => $admin->id,
    ]);

    actingAs($admin)
        ->get("/finished-inventory-movements/{$movement->id}/edit")
        ->assertNotFound();

    actingAs($admin)
        ->put("/finished-inventory-movements/{$movement->id}", [])
        ->assertMethodNotAllowed();

    actingAs($admin)
        ->delete("/finished-inventory-movements/{$movement->id}")
        ->assertMethodNotAllowed();
});

it('denies update and delete abilities even for admins', function () {
    [$admin, $product, $batch] = createFinishedMovementFixture('PROD-POLICY');
    $warehouse = Warehouse::factory()->create();

    $movement = FinishedInventoryMovement::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'finished_product_batch_id' => $batch->id,
        'type' => InventoryMovementType::Entry,
        'reason' => FinishedInventoryMovementReason::Adjustment,
        'quantity' => '10',
        'movement_date' => now(),
        'created_by' => $admin->id,
    ]);

    expect(Gate::forUser($admin)->allows('update', $movement))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('delete', $movement))->toBeFalse();
});

it('allows return up to the exited quantity of the batch in the same warehouse', function () {
    [$admin, , $batch] = createFinishedMovementFixture('PROD-RETURN');
    $warehouse = Warehouse::factory()->create();

    registerFinishedMovement($admin, $batch, $warehouse, 'entry', 'adjustment', '10');
    registerFinishedMovement($admin, $batch, $warehouse, 'exit', 'sale', '5');
    registerFinishedMovement($admin, $batch, $warehouse, 'entry', 'return', '5');

    actingAs($admin)
        ->post(route('finished-inventory-movements.store'), [
            'finished_product_batch_id' => $batch->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'entry',
            'reason' => 'return',
            'quantity' => '1',
            'movement_date' => now()->toDateString(),
        ])
        ->assertInvalid(['quantity']);
});

it('blocks return in a warehouse that never had exits for the batch', function () {
    [$admin, , $batch] = createFinishedMovementFixture('PROD-CROSS');
    $warehouseA = Warehouse::factory()->create();
    $warehouseB = Warehouse::factory()->create();

    registerFinishedMovement($admin, $batch, $warehouseA, 'entry', 'adjustment', '10');
    registerFinishedMovement($admin, $batch, $warehouseA, 'exit', 'sale', '5');

    actingAs($admin)
        ->post(route('finished-inventory-movements.store'), [
            'finished_product_batch_id' => $batch->id,
            'warehouse_id' => $warehouseB->id,
            'type' => 'entry',
            'reason' => 'return',
            'quantity' => '5',
            'movement_date' => now()->toDateString(),
        ])
        ->assertInvalid(['quantity']);
});

it('keeps returnable quotas independent per warehouse', function () {
    [$admin, , $batch] = createFinishedMovementFixture('PROD-INDEPENDENT');
    $warehouseA = Warehouse::factory()->create();
    $warehouseB = Warehouse::factory()->create();

    registerFinishedMovement($admin, $batch, $warehouseA, 'entry', 'adjustment', '10');
    registerFinishedMovement($admin, $batch, $warehouseB, 'entry', 'adjustment', '10');
    registerFinishedMovement($admin, $batch, $warehouseA, 'exit', 'sale', '5');
    registerFinishedMovement($admin, $batch, $warehouseB, 'exit', 'sale', '3');
    registerFinishedMovement($admin, $batch, $warehouseA, 'entry', 'return', '5');
    registerFinishedMovement($admin, $batch, $warehouseB, 'entry', 'return', '3');

    actingAs($admin)
        ->post(route('finished-inventory-movements.store'), [
            'finished_product_batch_id' => $batch->id,
            'warehouse_id' => $warehouseA->id,
            'type' => 'entry',
            'reason' => 'return',
            'quantity' => '1',
            'movement_date' => now()->toDateString(),
        ])
        ->assertInvalid(['quantity']);

    actingAs($admin)
        ->post(route('finished-inventory-movements.store'), [
            'finished_product_batch_id' => $batch->id,
            'warehouse_id' => $warehouseB->id,
            'type' => 'entry',
            'reason' => 'return',
            'quantity' => '1',
            'movement_date' => now()->toDateString(),
        ])
        ->assertInvalid(['quantity']);
});
