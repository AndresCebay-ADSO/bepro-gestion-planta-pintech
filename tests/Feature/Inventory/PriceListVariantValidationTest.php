<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function setupPriceListDependencies(): array
{
    $category = ProductCategory::create(['name' => 'Pinturas']);
    $uom = UnitOfMeasure::create(['code' => 'GAL', 'name' => 'Galón', 'symbol' => 'gal']);
    $user = User::create([
        'name' => 'Admin Prices',
        'email' => 'prices@example.com',
        'password' => Hash::make('password'),
    ]);

    $product = Product::create([
        'code' => 'PINT-001',
        'name' => 'Pintura Blanco',
        'category_id' => $category->id,
        'unit_of_measure_id' => $uom->id,
        'price_threshold' => 3,
    ]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'code' => '12345678',
        'name' => 'Pintura Blanco - 1 Galón',
        'unit_of_measure_id' => $uom->id,
        'presentation_value' => 1,
        'presentation_label' => '1 gal',
    ]);

    return [$product, $variant, $user];
}

test('permite crear precio con variante y completa product_id', function () {
    [$product, $variant, $user] = setupPriceListDependencies();

    $price = PriceList::create([
        'product_variant_id' => $variant->id,
        'price' => 150.1234,
        'cost_at_time' => 100.4321,
        'profit_margin' => 49.52,
        'update_type' => 'manual',
        'valid_from' => now()->toDateString(),
        'created_by' => $user->id,
    ]);

    expect((int) $price->product_id)->toBe((int) $product->id);
});

test('falla si producto y variante no corresponden', function () {
    [$product, $variant, $user] = setupPriceListDependencies();

    $anotherProduct = Product::create([
        'code' => 'PINT-002',
        'name' => 'Pintura Negro',
        'category_id' => $product->category_id,
        'unit_of_measure_id' => $product->unit_of_measure_id,
        'price_threshold' => 3,
    ]);

    expect(fn () => PriceList::create([
        'product_id' => $anotherProduct->id,
        'product_variant_id' => $variant->id,
        'price' => 160,
        'cost_at_time' => 110,
        'profit_margin' => 45,
        'update_type' => 'manual',
        'valid_from' => now()->toDateString(),
        'created_by' => $user->id,
    ]))->toThrow(ValidationException::class);
});
