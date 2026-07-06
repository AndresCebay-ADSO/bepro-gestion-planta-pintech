<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Transfer;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

uses(RefreshDatabase::class);

function setupTransferDependencies()
{
    $category = ProductCategory::create(['name' => 'Test Category']);
    $uom = UnitOfMeasure::create(['code' => 'L', 'name' => 'litro', 'symbol' => 'L']);
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

    $user = User::create([
        'name' => 'Test User',
        'email' => 'transfer@example.com',
        'password' => Hash::make('password'),
    ]);

    $factory = Warehouse::create([
        'name' => 'Cali Factory',
        'city' => 'Cali',
        'type' => 'factory',
    ]);

    $storage = Warehouse::create([
        'name' => 'Neiva Storage',
        'city' => 'Neiva',
        'type' => 'storage',
    ]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'code' => '12345678',
        'name' => 'Test Variant',
        'unit_of_measure_id' => $uom->id,
        'presentation_value' => 1,
        'presentation_label' => '1 gal',
    ]);

    return [$product, $variant, $user, $factory, $storage];
}

test('permite crear un traslado de fabrica a bodega', function () {
    [$product, $variant, $user, $factory, $storage] = setupTransferDependencies();

    $transfer = Transfer::create([
        'source_warehouse_id' => $factory->id,
        'destination_warehouse_id' => $storage->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'quantity' => 50,
        'status' => 'pending',
        'created_by' => $user->id,
    ]);

    expect($transfer->exists)->toBeTrue();
});

test('falla si el origen y destino son iguales', function () {
    [$product, $variant, $user, $factory, $storage] = setupTransferDependencies();

    expect(fn () => Transfer::create([
        'source_warehouse_id' => $factory->id,
        'destination_warehouse_id' => $factory->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'quantity' => 50,
        'status' => 'pending',
        'created_by' => $user->id,
    ]))->toThrow(InvalidArgumentException::class, 'La bodega de origen y destino no pueden ser la misma.');
});

test('falla si la cantidad es negativa', function () {
    [$product, $variant, $user, $factory, $storage] = setupTransferDependencies();

    expect(fn () => Transfer::create([
        'source_warehouse_id' => $factory->id,
        'destination_warehouse_id' => $storage->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'quantity' => -10,
        'status' => 'pending',
        'created_by' => $user->id,
    ]))->toThrow(InvalidArgumentException::class, 'La cantidad debe ser mayor a cero.');
});

test('falla si el origen no es fabrica', function () {
    [$product, $variant, $user, $factory, $storage] = setupTransferDependencies();

    expect(fn () => Transfer::create([
        'source_warehouse_id' => $storage->id,
        'destination_warehouse_id' => $factory->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'quantity' => 10,
        'status' => 'pending',
        'created_by' => $user->id,
    ]))->toThrow(InvalidArgumentException::class, 'Los traslados solo pueden originarse en una Fábrica.');
});

test('falla si el destino es fabrica', function () {
    [$product, $variant, $user, $factory, $storage] = setupTransferDependencies();

    expect(fn () => Transfer::create([
        'source_warehouse_id' => $factory->id,
        'destination_warehouse_id' => $factory->id, // This would trigger same-warehouse first, so we need another factory
    ]));

    $anotherFactory = Warehouse::create([
        'name' => 'Medellin Factory',
        'city' => 'Medellin',
        'type' => 'factory',
    ]);

    expect(fn () => Transfer::create([
        'source_warehouse_id' => $factory->id,
        'destination_warehouse_id' => $anotherFactory->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'quantity' => 10,
        'status' => 'pending',
        'created_by' => $user->id,
    ]))->toThrow(InvalidArgumentException::class, 'El destino de un traslado no puede ser una Fábrica.');
});

test('permite crear traslado solo con variante y completa product_id', function () {
    [$product, $variant, $user, $factory, $storage] = setupTransferDependencies();

    $transfer = Transfer::create([
        'source_warehouse_id' => $factory->id,
        'destination_warehouse_id' => $storage->id,
        'product_variant_id' => $variant->id,
        'quantity' => 12,
        'status' => 'pending',
        'created_by' => $user->id,
    ]);

    expect((int) $transfer->product_id)->toBe((int) $product->id);
});

test('falla si el producto no corresponde a la variante', function () {
    [$product, $variant, $user, $factory, $storage] = setupTransferDependencies();

    $anotherProduct = Product::create([
        'code' => 'TEST-999',
        'name' => 'Another Product',
        'category_id' => $product->category_id,
        'unit_of_measure_id' => $product->unit_of_measure_id,
        'price_threshold' => 3,
    ]);

    expect(fn () => Transfer::create([
        'source_warehouse_id' => $factory->id,
        'destination_warehouse_id' => $storage->id,
        'product_id' => $anotherProduct->id,
        'product_variant_id' => $variant->id,
        'quantity' => 10,
        'status' => 'pending',
        'created_by' => $user->id,
    ]))->toThrow(ValidationException::class);
});
