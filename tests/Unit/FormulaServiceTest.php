<?php

declare(strict_types=1);

use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\FormulaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('it throws when trying to calculate planned materials with incompatible units', function () {
    $user = User::factory()->create();
    $category = ProductCategory::create(['name' => 'Pinturas']);

    $liter = UnitOfMeasure::create([
        'code' => 'L',
        'name' => 'Litro',
        'symbol' => 'L',
        'to_liter_conversion' => 1,
    ]);

    $kilogram = UnitOfMeasure::create([
        'code' => 'KG',
        'name' => 'Kilogramo',
        'symbol' => 'kg',
        'to_kg_conversion' => 1,
    ]);

    $product = Product::create([
        'code' => 'P-FORM-01',
        'name' => 'Producto Fórmula',
        'category_id' => $category->id,
        'unit_of_measure_id' => $liter->id,
        'current_cost' => 10,
        'profit_margin' => 25,
        'current_price' => 12.5,
        'price_threshold' => 5,
    ]);

    $formula = Formula::create([
        'product_id' => $product->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    $rawMaterial = RawMaterial::create([
        'code' => 'RM-FORM-01',
        'unit_of_measure_id' => $kilogram->id,
        'current_price' => 1000,
        'is_active' => true,
    ]);

    FormulaDetail::create([
        'formula_id' => $formula->id,
        'raw_material_id' => $rawMaterial->id,
        'quantity' => 2,
        'unit_of_measure_id' => $kilogram->id,
    ]);

    $warehouse = Warehouse::create([
        'name' => 'Planta Formula Test',
        'city' => 'Cali',
        'type' => 'factory',
        'is_active' => true,
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-FORM-001',
        'product_id' => $product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'status' => 'pending',
        'planned_date' => now()->addDay(),
        'created_by' => $user->id,
    ]);

    $order->load(['formula.details.unitOfMeasure', 'product.unitOfMeasure']);

    expect(fn () => app(FormulaService::class)->calculatePlannedMaterials($order))
        ->toThrow(DomainException::class, "No se puede convertir entre 'kg' y 'L'");
});
