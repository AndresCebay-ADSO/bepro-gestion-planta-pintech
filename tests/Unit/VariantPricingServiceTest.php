<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\UnitOfMeasure;
use App\Services\VariantPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('it updates variant cost and refreshes price when threshold is met', function () {
    $category = ProductCategory::create(['name' => 'Pinturas']);
    $unit = UnitOfMeasure::create(['code' => 'GAL', 'name' => 'Galón', 'symbol' => 'gal']);

    $product = Product::create([
        'code' => 'P-PRICING-01',
        'name' => 'Producto Pricing',
        'category_id' => $category->id,
        'unit_of_measure_id' => $unit->id,
        'current_cost' => 10,
        'profit_margin' => 20,
        'current_price' => 12,
        'price_threshold' => 1,
    ]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'sku' => 'VAR-PRICE-01',
        'unit_of_measure_id' => $unit->id,
        'presentation_label' => 'Galón',
        'presentation_value' => 2,
        'current_cost' => 10,
        'current_price' => 12,
        'is_active' => true,
    ]);

    app(VariantPricingService::class)->updateVariantCostAndPrice(
        variant: $variant,
        bulkCost: 8,
        profitMargin: 25,
        priceThreshold: 1,
        packageUnitCost: 1,
        autoUpdatePrice: true,
        forceRefresh: false,
    );

    $variant->refresh();

    expect((float) $variant->current_cost)->toBe(17.0);
    expect((float) $variant->current_price)->toBe(21.25);
});

test('it updates variant cost without changing price when auto refresh is disabled', function () {
    $category = ProductCategory::create(['name' => 'Recubrimientos']);
    $unit = UnitOfMeasure::create(['code' => 'QT', 'name' => 'Cuarto', 'symbol' => 'qt']);

    $product = Product::create([
        'code' => 'P-PRICING-02',
        'name' => 'Producto Pricing 2',
        'category_id' => $category->id,
        'unit_of_measure_id' => $unit->id,
        'current_cost' => 10,
        'profit_margin' => 20,
        'current_price' => 14,
        'price_threshold' => 5,
    ]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'sku' => 'VAR-PRICE-02',
        'unit_of_measure_id' => $unit->id,
        'presentation_label' => 'Cuarto',
        'presentation_value' => 1,
        'current_cost' => 9,
        'current_price' => 14,
        'is_active' => true,
    ]);

    app(VariantPricingService::class)->updateVariantCostAndPrice(
        variant: $variant,
        bulkCost: 11,
        profitMargin: 30,
        priceThreshold: 1,
        packageUnitCost: 0.5,
        autoUpdatePrice: false,
        forceRefresh: false,
    );

    $variant->refresh();

    expect((float) $variant->current_cost)->toBe(11.5);
    expect((float) $variant->current_price)->toBe(14.0);
});
