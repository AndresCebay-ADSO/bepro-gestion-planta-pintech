<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->unit = UnitOfMeasure::create([
        'code' => 'kg',
        'name' => 'Kilogramo',
        'symbol' => 'kg',
    ]);

    $this->category = ProductCategory::create([
        'name' => 'Categoría Test',
    ]);
});

it('defaults sales_margin to null on product creation', function () {
    $product = Product::create([
        'name' => 'Producto Test',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'is_active' => true,
    ]);

    expect($product->sales_margin)->toBeNull();
    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'sales_margin' => null,
    ]);
});

it('accepts sales_margin values between 0 and 100', function () {
    $product = Product::create([
        'name' => 'Producto Margen',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'sales_margin' => 15.50,
        'is_active' => true,
    ]);

    expect((float) $product->sales_margin)->toBe(15.50);
    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'sales_margin' => '15.50',
    ]);
});

it('accepts zero as sales_margin', function () {
    $product = Product::create([
        'name' => 'Producto Sin Margen',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'sales_margin' => 0,
        'is_active' => true,
    ]);

    expect((float) $product->sales_margin)->toBe(0.0);
});

it('accepts maximum valid sales_margin of 99.99', function () {
    $product = Product::create([
        'name' => 'Producto Margen Max',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'sales_margin' => 99.99,
        'is_active' => true,
    ]);

    expect((float) $product->sales_margin)->toBe(99.99);
});
