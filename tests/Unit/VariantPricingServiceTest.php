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
        'cif_percentage' => 20,
        'current_price' => 12,
        'price_threshold' => 1,
    ]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'code' => 'VAR-PRICE-01',
        'name' => 'Producto Pricing - Galón',
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
        cifPercentage: 25,
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
        'cif_percentage' => 20,
        'current_price' => 14,
        'price_threshold' => 5,
    ]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'code' => 'VAR-PRICE-02',
        'name' => 'Producto Pricing 2 - Cuarto',
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
        cifPercentage: 30,
        priceThreshold: 1,
        packageUnitCost: 0.5,
        autoUpdatePrice: false,
        forceRefresh: false,
    );

    $variant->refresh();

    expect((float) $variant->current_cost)->toBe(11.5);
    expect((float) $variant->current_price)->toBe(14.0);
});

test('it refreshes price when previous cost and current price are zero', function () {
    $category = ProductCategory::create(['name' => 'Impermeabilizantes']);
    $unit = UnitOfMeasure::create(['code' => 'GL0', 'name' => 'Galón cero', 'symbol' => 'gl']);

    $product = Product::create([
        'code' => 'P-PRICING-ZERO-01',
        'name' => 'Producto Costo Cero',
        'category_id' => $category->id,
        'unit_of_measure_id' => $unit->id,
        'current_cost' => 0,
        'cif_percentage' => 25,
        'current_price' => 0,
        'price_threshold' => 5,
    ]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'code' => 'VAR-PRICE-ZERO-01',
        'name' => 'Producto Costo Cero',
        'unit_of_measure_id' => $unit->id,
        'presentation_value' => 1,
        'current_cost' => 0,
        'current_price' => 0,
        'is_active' => true,
    ]);

    app(VariantPricingService::class)->updateVariantCostAndPrice(
        variant: $variant,
        bulkCost: 18.5,
        cifPercentage: 25,
        priceThreshold: 5,
        packageUnitCost: 0,
        autoUpdatePrice: true,
        forceRefresh: false,
    );

    $variant->refresh();

    expect((float) $variant->current_cost)->toBe(18.5);
    expect((float) $variant->current_price)->toBe(23.125);
});

test('it refreshes price when previous cost is zero and current price exists', function () {
    $category = ProductCategory::create(['name' => 'Selladores']);
    $unit = UnitOfMeasure::create(['code' => 'GL1', 'name' => 'Galón uno', 'symbol' => 'gl']);

    $product = Product::create([
        'code' => 'P-PRICING-ZERO-02',
        'name' => 'Producto Sin Historial',
        'category_id' => $category->id,
        'unit_of_measure_id' => $unit->id,
        'current_cost' => 0,
        'cif_percentage' => 20,
        'current_price' => 12,
        'price_threshold' => 50,
    ]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'code' => 'VAR-PRICE-ZERO-02',
        'name' => 'Producto Sin Historial',
        'unit_of_measure_id' => $unit->id,
        'presentation_value' => 1,
        'current_cost' => 0,
        'current_price' => 12,
        'is_active' => true,
    ]);

    app(VariantPricingService::class)->updateVariantCostAndPrice(
        variant: $variant,
        bulkCost: 18.5,
        cifPercentage: 20,
        priceThreshold: 50,
        packageUnitCost: 0,
        autoUpdatePrice: true,
        forceRefresh: false,
    );

    $variant->refresh();

    expect((float) $variant->current_cost)->toBe(18.5);
    expect((float) $variant->current_price)->toBe(22.2);
});

test('it refreshes price when current price is zero even below threshold', function () {
    $category = ProductCategory::create(['name' => 'Esmaltes']);
    $unit = UnitOfMeasure::create(['code' => 'GL2', 'name' => 'Galón dos', 'symbol' => 'gl']);

    $product = Product::create([
        'code' => 'P-PRICING-ZERO-03',
        'name' => 'Producto Precio Cero',
        'category_id' => $category->id,
        'unit_of_measure_id' => $unit->id,
        'current_cost' => 10,
        'cif_percentage' => 20,
        'current_price' => 0,
        'price_threshold' => 5,
    ]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'code' => 'VAR-PRICE-ZERO-03',
        'name' => 'Producto Precio Cero',
        'unit_of_measure_id' => $unit->id,
        'presentation_value' => 1,
        'current_cost' => 10,
        'current_price' => 0,
        'is_active' => true,
    ]);

    app(VariantPricingService::class)->updateVariantCostAndPrice(
        variant: $variant,
        bulkCost: 10.2,
        cifPercentage: 20,
        priceThreshold: 5,
        packageUnitCost: 0,
        autoUpdatePrice: true,
        forceRefresh: false,
    );

    $variant->refresh();

    expect((float) $variant->current_cost)->toBe(10.2);
    expect((float) $variant->current_price)->toBe(12.24);
});

test('it keeps valid price when cost variation is below threshold', function () {
    $category = ProductCategory::create(['name' => 'Barnices']);
    $unit = UnitOfMeasure::create(['code' => 'GL3', 'name' => 'Galón tres', 'symbol' => 'gl']);

    $product = Product::create([
        'code' => 'P-PRICING-THRESHOLD-01',
        'name' => 'Producto Umbral',
        'category_id' => $category->id,
        'unit_of_measure_id' => $unit->id,
        'current_cost' => 10,
        'cif_percentage' => 20,
        'current_price' => 12,
        'price_threshold' => 5,
    ]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'code' => 'VAR-PRICE-THRESHOLD-01',
        'name' => 'Producto Umbral',
        'unit_of_measure_id' => $unit->id,
        'presentation_value' => 1,
        'current_cost' => 10,
        'current_price' => 12,
        'is_active' => true,
    ]);

    app(VariantPricingService::class)->updateVariantCostAndPrice(
        variant: $variant,
        bulkCost: 10.2,
        cifPercentage: 20,
        priceThreshold: 5,
        packageUnitCost: 0,
        autoUpdatePrice: true,
        forceRefresh: false,
    );

    $variant->refresh();

    expect((float) $variant->current_cost)->toBe(10.2);
    expect((float) $variant->current_price)->toBe(12.0);
});
