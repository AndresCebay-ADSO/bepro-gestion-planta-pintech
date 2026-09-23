<?php

declare(strict_types=1);

use App\Enums\FinishedInventoryMovementReason;
use App\Enums\InventoryMovementType;
use App\Enums\SystemRole;
use App\Models\FinishedInventoryMovement;
use App\Models\FinishedProductBatch;
use App\Models\FinishedProductBatchStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('allows admin and produccion to access finished inventory movements index', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $produccion = User::factory()->create()->assignRole(SystemRole::Production->value);

    actingAs($admin)
        ->get(route('finished-inventory-movements.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Inventory/FinishedMovements/Index')
            ->has('movements.data')
            ->where('can.create', true)
        );

    actingAs($produccion)
        ->get(route('finished-inventory-movements.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Inventory/FinishedMovements/Index')
            ->has('movements.data')
            ->where('can.create', true)
        );
});

it('forbids comercial from accessing finished inventory movements index', function () {
    $comercial = User::factory()->create()->assignRole(SystemRole::Commercial->value);

    actingAs($comercial)
        ->get(route('finished-inventory-movements.index'))
        ->assertForbidden();
});

it('allows comercial to access finished inventory index', function () {
    $comercial = User::factory()->create()->assignRole(SystemRole::Commercial->value);

    actingAs($comercial)
        ->get(route('finished-inventory.index'))
        ->assertOk();
});

it('allows operador to see finished inventory but not its movements', function () {
    $operador = User::factory()->create()->assignRole(SystemRole::Operator->value);

    actingAs($operador)
        ->get(route('finished-inventory.index'))
        ->assertOk();

    actingAs($operador)
        ->get(route('finished-inventory-movements.index'))
        ->assertForbidden();
});

it('exposes finished product batches from all warehouses for movement forms', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $warehouseA = Warehouse::factory()->factory()->create();
    $warehouseB = Warehouse::factory()->storage()->create();
    $unit = UnitOfMeasure::factory()->create();
    $category = ProductCategory::create(['name' => 'PT']);
    $product = Product::create([
        'code' => 'PT-001',
        'name' => 'Producto terminado',
        'brand' => 'BEPRO',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    $batchInCurrentWarehouse = FinishedProductBatch::create([
        'product_id' => $product->id,
        'initial_quantity' => '10',
        'entry_date' => now()->subDay()->toDateString(),
    ]);
    $batchInOtherWarehouse = FinishedProductBatch::create([
        'product_id' => $product->id,
        'initial_quantity' => '20',
        'entry_date' => now()->toDateString(),
    ]);

    FinishedProductBatchStock::create([
        'finished_product_batch_id' => $batchInCurrentWarehouse->id,
        'warehouse_id' => $warehouseA->id,
        'quantity' => '10',
    ]);
    FinishedProductBatchStock::create([
        'finished_product_batch_id' => $batchInOtherWarehouse->id,
        'warehouse_id' => $warehouseB->id,
        'quantity' => '20',
    ]);

    $response = $this
        ->withSession(['current_warehouse_id' => $warehouseA->id])
        ->actingAs($admin)
        ->get(route('finished-inventory-movements.index'));

    $response->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Inventory/FinishedMovements/Index')
            ->where('currentWarehouseId', $warehouseA->id)
            ->reloadOnly('batches', fn (AssertableInertia $reload) => $reload
                ->has('batches', 2)
                ->where('batches.0.id', $batchInCurrentWarehouse->id)
                ->where('batches.0.stocks.0.warehouse_id', $warehouseA->id)
                ->where('batches.1.id', $batchInOtherWarehouse->id)
                ->where('batches.1.stocks.0.warehouse_id', $warehouseB->id)
            )
        );
});

it('shows finished inventory movement costs only to users with costs.view', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $produccion = User::factory()->create()->assignRole(SystemRole::Production->value);
    $warehouse = Warehouse::factory()->create();
    $unit = UnitOfMeasure::factory()->create();
    $category = ProductCategory::create(['name' => 'PT costos']);
    $product = Product::create([
        'code' => 'PT-COST-001',
        'name' => 'Producto con costo',
        'brand' => 'BEPRO',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'is_active' => true,
    ]);
    $batch = FinishedProductBatch::create([
        'product_id' => $product->id,
        'initial_quantity' => '10',
        'entry_date' => now()->toDateString(),
    ]);
    $movement = FinishedInventoryMovement::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'finished_product_batch_id' => $batch->id,
        'type' => InventoryMovementType::Entry,
        'reason' => FinishedInventoryMovementReason::Production,
        'quantity' => '10',
        'cost_price' => '12.5',
        'movement_date' => now()->toDateString(),
        'created_by' => $admin->id,
    ]);

    actingAs($produccion)
        ->get(route('finished-inventory-movements.show', $movement))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->missing('movement.cost_price'));

    actingAs($produccion)
        ->get(route('finished-inventory-movements.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('movements.data', 1)
            ->missing('movements.data.0.cost_price'));

    actingAs($admin)
        ->get(route('finished-inventory-movements.show', $movement))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('movement.cost_price'));
});
