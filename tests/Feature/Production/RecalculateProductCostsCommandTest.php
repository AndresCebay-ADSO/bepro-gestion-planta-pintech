<?php

declare(strict_types=1);

use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it recalculates one product and updates variant prices from command', function () {
    $unit = UnitOfMeasure::create([
        'code' => 'kg',
        'name' => 'Kilogramo',
        'symbol' => 'kg',
    ]);

    $category = ProductCategory::create([
        'name' => 'Pinturas',
    ]);

    $product = Product::create([
        'code' => 'P-CMD-001',
        'name' => 'Producto Cmd',
        'category_id' => $category->id,
        'unit_of_measure_id' => $unit->id,
        'current_cost' => null,
        'cif_percentage' => 20,
        'current_price' => null,
        'price_threshold' => 0,
        'is_active' => true,
    ]);

    $rawMaterial = RawMaterial::create([
        'code' => 'RM-CMD-001',
        'unit_of_measure_id' => $unit->id,
        'current_price' => 12,
        'previous_price' => null,
        'minimum_stock' => 0,
        'alert_days_before_expiry' => 30,
        'is_active' => true,
    ]);

    $packageMaterial = RawMaterial::create([
        'code' => 'ENV-CMD-001',
        'unit_of_measure_id' => $unit->id,
        'current_price' => 8,
        'previous_price' => null,
        'minimum_stock' => 0,
        'alert_days_before_expiry' => 30,
        'is_active' => true,
    ]);

    $formula = Formula::create([
        'product_id' => $product->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => User::factory()->create()->id,
    ]);

    FormulaDetail::create([
        'formula_id' => $formula->id,
        'raw_material_id' => $rawMaterial->id,
        'quantity' => 2,
        'unit_of_measure_id' => $unit->id,
    ]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'code' => 'P-CMD-001-5GL',
        'name' => 'Producto Cmd - Cuñete 5G',
        'unit_of_measure_id' => $unit->id,
        'presentation_value' => 5,
        'package_raw_material_id' => $packageMaterial->id,
        'current_cost' => null,
        'current_price' => null,
        'is_active' => true,
    ]);

    $this->artisan('costs:recalculate-product', ['product_id' => $product->id])
        ->assertExitCode(0);

    $product->refresh();
    $variant->refresh();

    expect((float) $product->current_cost)->toBe(24.0); // 2 * 12
    expect((float) $product->current_price)->toBe(28.8); // +20%
    expect((float) $variant->current_cost)->toBe(128.0); // (24*5)+8
    expect((float) $variant->current_price)->toBe(153.6); // +20%
});

test('it fails when product id does not exist', function () {
    $this->artisan('costs:recalculate-product', ['product_id' => 999999])
        ->assertExitCode(1);
});
