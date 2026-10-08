<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\ProductVariant;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

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
        'cif_percentage' => 50,
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
        'lot_number' => fake()->unique()->numberBetween(100000, 999999),
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
        'lot_number' => fake()->unique()->numberBetween(100000, 999999),
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
    test()->seed(RolePermissionSeeder::class);

    [$product, $user, $formula] = createDependencies();
    $user->forceFill(['email_verified_at' => now()])->save();
    $user->assignRole(SystemRole::Admin->value);

    $otherProduct = Product::create([
        'code' => 'TEST-002',
        'name' => 'Otro Producto',
        'category_id' => $product->category_id,
        'unit_of_measure_id' => $product->unit_of_measure_id,
        'current_cost' => 12,
        'cif_percentage' => 35,
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

test('rejects an inactive formula_id', function () {
    test()->seed(RolePermissionSeeder::class);

    [$product, $user, $formula] = createDependencies();
    $user->forceFill(['email_verified_at' => now()])->save();
    $user->assignRole(SystemRole::Admin->value);

    $warehouse = Warehouse::create([
        'name' => 'Fábrica Yumbo',
        'city' => 'Yumbo',
        'type' => 'factory',
        'is_active' => true,
    ]);

    $formula->update(['is_active' => false]);

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

// B55: una presentación va una sola vez por orden. El error viaja por fila (`packaging.N.product_variant_id`): la
// pantalla de creación lo muestra en la fila repetida.
test('rejects a repeated presentation when creating an order, with the error on the row', function () {
    test()->seed(RolePermissionSeeder::class);

    [$product, $user, $formula] = createDependencies();
    $user->forceFill(['email_verified_at' => now()])->save();
    $user->assignRole(SystemRole::Admin->value);

    $warehouse = Warehouse::create([
        'name' => 'Fábrica Cali',
        'city' => 'Cali',
        'type' => 'factory',
        'is_active' => true,
    ]);
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'code' => 'TEST-001-GAL',
        'name' => 'Test Product - Galón',
        'unit_of_measure_id' => $product->unit_of_measure_id,
        'presentation_value' => 1,
        'presentation_label' => 'Galón',
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)
        ->from(route('production-orders.create'))
        ->post(route('production-orders.store'), [
            'product_id' => $product->id,
            'formula_id' => $formula->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'planned_date' => now()->addDay()->toDateString(),
            'packaging' => [
                ['product_variant_id' => $variant->id, 'planned_units' => 6],
                ['product_variant_id' => $variant->id, 'planned_units' => 4],
            ],
        ]);

    $response->assertRedirect(route('production-orders.create'));
    $response->assertSessionHasErrors([
        'packaging.1.product_variant_id' => 'No puedes repetir la misma presentación de empaque en la planificación.',
    ]);
    expect(ProductionOrder::query()->count())->toBe(0);
});

// B55: el número de lote identifica el lote en documentos, QR y movimientos de PT; la base no deja que falte ni se repita.
test('the database requires a lot number on every order', function () {
    [$product, $user, $formula] = createDependencies();
    $warehouse = Warehouse::create(['name' => 'Fábrica', 'city' => 'Cali', 'type' => 'factory']);

    expect(fn () => ProductionOrder::create([
        'order_number' => 'OP-2026-0001',
        'product_id' => $product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'status' => 'pending',
        'planned_date' => now(),
        'created_by' => $user->id,
    ]))->toThrow(QueryException::class);
});

test('the database rejects a repeated lot number', function () {
    [$product, $user, $formula] = createDependencies();
    $warehouse = Warehouse::create(['name' => 'Fábrica', 'city' => 'Cali', 'type' => 'factory']);
    $attributes = [
        'lot_number' => 1620,
        'product_id' => $product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'status' => 'pending',
        'planned_date' => now(),
        'created_by' => $user->id,
    ];
    ProductionOrder::create(['order_number' => 'OP-2026-0001', ...$attributes]);

    expect(fn () => ProductionOrder::create(['order_number' => 'OP-2026-0002', ...$attributes]))
        ->toThrow(QueryException::class);
});

// 3.4: el color es opcional y tiene el mismo tope que el de la cotización y el pedido.
test('rejects an order color longer than 100 characters', function () {
    test()->seed(RolePermissionSeeder::class);

    [$product, $user, $formula] = createDependencies();
    $user->forceFill(['email_verified_at' => now()])->save();
    $user->assignRole(SystemRole::Admin->value);
    $warehouse = Warehouse::create(['name' => 'Fábrica Cali', 'city' => 'Cali', 'type' => 'factory', 'is_active' => true]);

    $this->actingAs($user)
        ->from(route('production-orders.create'))
        ->post(route('production-orders.store'), [
            'product_id' => $product->id,
            'formula_id' => $formula->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'planned_date' => now()->addDay()->toDateString(),
            'color' => str_repeat('R', 101),
        ])
        ->assertSessionHasErrors('color');

    expect(ProductionOrder::query()->count())->toBe(0);
});
