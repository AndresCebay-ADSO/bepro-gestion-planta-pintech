<?php

use App\Models\FinishedProductBatch;
use App\Models\FinishedProductBatchStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'produccion']);
    Role::firstOrCreate(['name' => 'comercial']);
});

it('allows admin and produccion to access finished inventory movements index', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $produccion = User::factory()->create()->assignRole('produccion');

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
    $comercial = User::factory()->create()->assignRole('comercial');

    actingAs($comercial)
        ->get(route('finished-inventory-movements.index'))
        ->assertForbidden();
});

it('allows comercial to access finished inventory index', function () {
    $comercial = User::factory()->create()->assignRole('comercial');

    actingAs($comercial)
        ->get(route('finished-inventory.index'))
        ->assertOk();
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
