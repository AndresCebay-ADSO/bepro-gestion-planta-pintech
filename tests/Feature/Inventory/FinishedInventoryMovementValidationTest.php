<?php

use App\Enums\FinishedInventoryMovementReason;
use App\Enums\InventoryMovementType;
use App\Models\FinishedInventoryMovement;
use App\Models\FinishedProductBatch;
use App\Models\FinishedProductBatchStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin']);
});

it('fails update validation if exit movement selects a batch with no stock in that warehouse', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $warehouse = Warehouse::factory()->create();
    $unit = UnitOfMeasure::factory()->create();
    $category = ProductCategory::create(['name' => 'Cat Test']);

    $product = Product::create([
        'code' => 'PROD-TEST',
        'name' => 'Producto Test',
        'brand' => 'BEPRO',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    $batch = FinishedProductBatch::create([
        'product_id' => $product->id,
        'initial_quantity' => '100',
        'entry_date' => now(),
    ]);

    // Create a movement we want to edit
    $movement = FinishedInventoryMovement::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'finished_product_batch_id' => $batch->id,
        'type' => InventoryMovementType::Exit,
        'reason' => FinishedInventoryMovementReason::Sale,
        'quantity' => '10',
        'movement_date' => now(),
        'created_by' => $admin->id,
    ]);

    // The batch has no stock record yet (or we could explicitly create one with 0 quantity)
    FinishedProductBatchStock::create([
        'finished_product_batch_id' => $batch->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => '0', // No stock!
    ]);

    actingAs($admin)
        ->put(route('finished-inventory-movements.update', $movement), [
            'finished_product_batch_id' => $batch->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'exit',
            'reason' => 'sale',
            'quantity' => '5',
            'movement_date' => now()->toDateString(),
        ])
        ->assertInvalid(['finished_product_batch_id' => 'El lote seleccionado no tiene stock disponible en la bodega indicada.']);
});

it('fails update validation if reason is changed to Transfer', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $warehouse = Warehouse::factory()->create();
    $unit = UnitOfMeasure::factory()->create();
    $category = ProductCategory::create(['name' => 'Cat Test 2']);

    $product = Product::create([
        'code' => 'PROD-TEST-2',
        'name' => 'Producto Test 2',
        'brand' => 'BEPRO',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    $batch = FinishedProductBatch::create([
        'product_id' => $product->id,
        'initial_quantity' => '100',
        'entry_date' => now(),
    ]);

    $movement = FinishedInventoryMovement::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'finished_product_batch_id' => $batch->id,
        'type' => InventoryMovementType::Entry,
        'reason' => FinishedInventoryMovementReason::Production,
        'quantity' => '10',
        'movement_date' => now(),
        'created_by' => $admin->id,
    ]);

    actingAs($admin)
        ->put(route('finished-inventory-movements.update', $movement), [
            'finished_product_batch_id' => $batch->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'entry',
            'reason' => 'transfer',
            'quantity' => '10',
            'movement_date' => now()->toDateString(),
        ])
        ->assertInvalid(['reason' => 'No se puede cambiar un movimiento existente a Traslado. Los traslados se crean como pares desde cero.']);
});
