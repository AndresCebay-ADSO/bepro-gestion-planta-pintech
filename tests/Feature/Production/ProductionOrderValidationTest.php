<?php

declare(strict_types=1);

use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * @return array{0: Product, 1: User, 2: Formula}
 */
function createDependencies(): array
{
    $category = ProductCategory::create(['name' => 'Test Category']);
    $uom = UnitOfMeasure::create([
        'code' => 'L',
        'name' => 'litro',
        'symbol' => 'L',
    ]);

    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

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

    $formula = Formula::create([
        'product_id' => $product->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    return [$product, $user, $formula];
}

test('guarda una orden de produccion si la bodega es de tipo fabrica', function () {
    [$product, $user, $formula] = createDependencies();
    $warehouse = Warehouse::create([
        'name' => 'Fábrica Cali',
        'city' => 'Cali',
        'type' => 'factory',
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-001',
        'product_id' => $product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 100,
        'status' => 'pending',
        'planned_date' => now(),
        'created_by' => $user->id,
    ]);

    expect($order->exists)->toBeTrue();
});

test('lanza excepcion si se intenta guardar una orden de produccion en una bodega tipo bodega', function () {
    [$product, $user, $formula] = createDependencies();
    $warehouse = Warehouse::create([
        'name' => 'Bodega Neiva',
        'city' => 'Neiva',
        'type' => 'storage',
    ]);

    expect(fn () => ProductionOrder::create([
        'order_number' => 'OP-002',
        'product_id' => $product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 100,
        'status' => 'pending',
        'planned_date' => now(),
        'created_by' => $user->id,
    ]))->toThrow(InvalidArgumentException::class, 'Solo se pueden asociar órdenes de producción a bodegas tipo Fábrica.');
});

test('rejects formula_id that belongs to another product', function () {
    Role::create(['name' => 'admin']);

    [$product, $user, $formula] = createDependencies();
    $user->forceFill(['email_verified_at' => now()])->save();
    $user->assignRole('admin');

    $otherProduct = Product::create([
        'code' => 'TEST-002',
        'name' => 'Otro Producto',
        'category_id' => $product->category_id,
        'unit_of_measure_id' => $product->unit_of_measure_id,
        'current_cost' => 12,
        'profit_margin' => 35,
        'current_price' => 16.2,
        'price_threshold' => 5,
    ]);

    $warehouse = Warehouse::create([
        'name' => 'Fábrica Palmira',
        'city' => 'Palmira',
        'type' => 'factory',
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)
        ->from(route('production-orders.create'))
        ->post(route('production-orders.store'), [
            'product_id' => $otherProduct->id,
            'formula_id' => $formula->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'planned_date' => now()->addDay()->toDateString(),
        ]);

    $response->assertRedirect(route('production-orders.create'));
    $response->assertSessionHasErrors(['formula_id']);
});

test('rejects soft deleted formula_id', function () {
    Role::create(['name' => 'admin']);

    [$product, $user, $formula] = createDependencies();
    $user->forceFill(['email_verified_at' => now()])->save();
    $user->assignRole('admin');

    $warehouse = Warehouse::create([
        'name' => 'Fábrica Yumbo',
        'city' => 'Yumbo',
        'type' => 'factory',
        'is_active' => true,
    ]);

    $formula->delete();

    $response = $this->actingAs($user)
        ->from(route('production-orders.create'))
        ->post(route('production-orders.store'), [
            'product_id' => $product->id,
            'formula_id' => $formula->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'planned_date' => now()->addDay()->toDateString(),
        ]);

    $response->assertRedirect(route('production-orders.create'));
    $response->assertSessionHasErrors(['formula_id']);
});
