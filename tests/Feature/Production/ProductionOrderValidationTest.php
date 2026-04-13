<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function createDependencies()
{
    $category = ProductCategory::create(['name' => 'Test Category']);
    $uom = UnitOfMeasure::create(['name' => 'litro', 'abbreviation' => 'L']);
    $product = Product::create([
        'code' => 'TEST-001',
        'name' => 'Test Product',
        'category_id' => $category->id,
        'unit_of_measure_id' => $uom->id,
        'current_cost' => 10,
        'profit_margin' => 50,
        'current_price' => 15,
        'price_threshold' => 5,
    ]);
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

    return [$product, $user];
}

test('guarda una orden de produccion si la bodega es de tipo fabrica', function () {
    [$product, $user] = createDependencies();
    $warehouse = Warehouse::create([
        'name' => 'Fábrica Cali',
        'city' => 'Cali',
        'type' => 'fabrica',
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-001',
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 100,
        'status' => 'pendiente',
        'created_by' => $user->id,
    ]);

    expect($order->exists)->toBeTrue();
});

test('lanza excepcion si se intenta guardar una orden de produccion en una bodega tipo bodega', function () {
    [$product, $user] = createDependencies();
    $warehouse = Warehouse::create([
        'name' => 'Bodega Neiva',
        'city' => 'Neiva',
        'type' => 'bodega',
    ]);

    expect(fn () => ProductionOrder::create([
        'order_number' => 'OP-002',
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 100,
        'status' => 'pendiente',
        'created_by' => $user->id,
    ]))->toThrow(InvalidArgumentException::class, 'Solo se pueden asociar órdenes de producción a bodegas tipo Fábrica.');
});
