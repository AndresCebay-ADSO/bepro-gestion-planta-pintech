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
        'name' => 'Producto Fórmula',
        'category_id' => $category->id,
        'unit_of_measure_id' => $liter->id,
        'current_cost' => 10,
        'cif_percentage' => 25,
        'current_price' => 12.5,
        'price_threshold' => 5,
    ]);

    $formula = Formula::create([
        'product_id' => $product->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    // Materia prima en Litros
    $rawMaterial = RawMaterial::create([
        'code' => 'RM-FORM-01',
        'unit_of_measure_id' => $liter->id,
        'current_price' => 1000,
        'is_active' => true,
    ]);

    // Detalle de fórmula especifica Kilogramos (Incompatible)
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
        'lot_number' => fake()->unique()->numberBetween(100000, 999999),
        'product_id' => $product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'status' => 'pending',
        'planned_date' => now()->addDay(),
        'created_by' => $user->id,
    ]);

    $order->load(['formula.details.unitOfMeasure', 'formula.details.rawMaterial.unitOfMeasure']);

    expect(fn () => app(FormulaService::class)->calculatePlannedMaterials($order))
        ->toThrow(DomainException::class, "No se puede convertir entre 'kg' y 'L'");
});

test('it calculates planned materials and converts units successfully', function () {
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

    $gram = UnitOfMeasure::create([
        'code' => 'G',
        'name' => 'Gramo',
        'symbol' => 'g',
        'to_kg_conversion' => 0.001,
    ]);

    $product = Product::create([
        'name' => 'Producto Fórmula 2',
        'category_id' => $category->id,
        'unit_of_measure_id' => $liter->id,
        'current_cost' => 10,
        'cif_percentage' => 25,
        'current_price' => 12.5,
        'price_threshold' => 5,
    ]);

    $formula = Formula::create([
        'product_id' => $product->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    // Materia prima en Kilogramos
    $rawMaterial = RawMaterial::create([
        'code' => 'RM-FORM-02',
        'unit_of_measure_id' => $kilogram->id,
        'current_price' => 1000,
        'is_active' => true,
    ]);

    // Detalle de fórmula en Gramos
    FormulaDetail::create([
        'formula_id' => $formula->id,
        'raw_material_id' => $rawMaterial->id,
        'quantity' => 250,
        'unit_of_measure_id' => $gram->id,
    ]);

    $warehouse = Warehouse::create([
        'name' => 'Planta Formula Test 2',
        'city' => 'Cali',
        'type' => 'factory',
        'is_active' => true,
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-FORM-002',
        'lot_number' => fake()->unique()->numberBetween(100000, 999999),
        'product_id' => $product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10, // 10 unidades
        'status' => 'pending',
        'planned_date' => now()->addDay(),
        'created_by' => $user->id,
    ]);

    $plannedMaterials = app(FormulaService::class)->calculatePlannedMaterials($order);

    expect($plannedMaterials)->toHaveCount(1);
    expect($plannedMaterials->first())->toEqual([
        'raw_material_id' => $rawMaterial->id,
        'planned_quantity' => 2.5, // 250g * 0.001 * 10
        'unit_of_measure_id' => $kilogram->id, // Debe ser la unidad base de la materia prima
    ]);
});

// La conversión no pasa por un factor redondeado: con el factor a 6 decimales, mL → galón perdía un 0,065 % (0,000264 en
// vez de 0,000264172…), y ese error llegaba al costo de la fórmula (auditoría 3.6, PR 9).
test('it converts quantities between units without rounding an intermediate factor', function () {
    $milliliter = UnitOfMeasure::create(['code' => 'mlf', 'name' => 'Mililitro', 'symbol' => 'mL', 'to_liter_conversion' => '0.001']);
    $gallon = UnitOfMeasure::create(['code' => 'glf', 'name' => 'Galón', 'symbol' => 'gal', 'to_liter_conversion' => '3.7854']);
    $service = app(FormulaService::class);

    expect($service->convertQuantity('1000', $milliliter, $gallon))->toBe('0.2641728747')
        ->and($service->convertQuantity('2.5', $gallon, $milliliter))->toBe('9463.5000000000')
        ->and($service->convertQuantity('2.5', $gallon, $gallon))->toBe('2.5000000000')
        // El factor que se guarda en la línea de la OP conserva los 6 decimales de su columna.
        ->and($service->getConversionFactor($milliliter, $gallon))->toBe('0.000264')
        ->and($service->getConversionFactor($gallon, $milliliter))->toBe('3785.400000');
});

test('it tells whether two units can be converted', function () {
    $kilogram = UnitOfMeasure::create(['code' => 'kgc', 'name' => 'Kilo', 'symbol' => 'kgc', 'to_kg_conversion' => '1']);
    $gram = UnitOfMeasure::create(['code' => 'gc', 'name' => 'Gramo', 'symbol' => 'gc', 'to_kg_conversion' => '0.001']);
    $liter = UnitOfMeasure::create(['code' => 'lc', 'name' => 'Litro', 'symbol' => 'lc', 'to_liter_conversion' => '1']);
    $unit = UnitOfMeasure::create(['code' => 'uc', 'name' => 'Unidad', 'symbol' => 'uc']);
    $service = app(FormulaService::class);

    expect($service->canConvert($gram, $kilogram))->toBeTrue()
        ->and($service->canConvert($unit, $unit))->toBeTrue()
        ->and($service->canConvert($kilogram, $liter))->toBeFalse()
        ->and($service->canConvert($unit, $kilogram))->toBeFalse()
        ->and(fn () => $service->convertQuantity('1', $kilogram, $liter))->toThrow(DomainException::class);
});
