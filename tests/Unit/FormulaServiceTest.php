<?php

declare(strict_types=1);

use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Services\FormulaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Una línea de fórmula con sus unidades cargadas, como la recibe orderLineRequirement().
 */
function formulaLine(UnitOfMeasure $lineUnit, UnitOfMeasure $materialUnit, string $quantity): FormulaDetail
{
    $detail = FormulaDetail::create([
        'formula_id' => Formula::factory()->create()->id,
        'raw_material_id' => RawMaterial::factory()->create(['unit_of_measure_id' => $materialUnit->id])->id,
        'quantity' => $quantity,
        'unit_of_measure_id' => $lineUnit->id,
        'step_order' => 1,
    ]);

    return $detail->load(['unitOfMeasure', 'rawMaterial.unitOfMeasure']);
}

test('it throws when a formula line unit cannot convert to the raw material unit', function () {
    $liter = UnitOfMeasure::factory()->create(['symbol' => 'L', 'to_liter_conversion' => 1]);
    $kilogram = UnitOfMeasure::factory()->create(['symbol' => 'kg', 'to_kg_conversion' => 1]);

    expect(fn () => app(FormulaService::class)->orderLineRequirement(formulaLine($kilogram, $liter, '2'), '10'))
        ->toThrow(DomainException::class, "No se puede convertir entre 'kg' y 'L'");
});

test('it computes what a formula line asks of an order, in the raw material unit', function () {
    $gram = UnitOfMeasure::factory()->create(['to_kg_conversion' => 0.001]);
    $kilogram = UnitOfMeasure::factory()->create(['to_kg_conversion' => 1]);

    // 250 g por galón × 10 galones = 2,5 kg.
    expect(app(FormulaService::class)->orderLineRequirement(formulaLine($gram, $kilogram, '250'), '10'))
        ->toBe(['planned_quantity' => '2.5000', 'conversion_factor' => '0.001000']);
});

// La OP calcula con el factor que guarda (6 decimales), el mismo con que la pantalla convierte el consumo real: así plan
// y consumo cuadran. El costo de lista usa convertQuantity(), exacto. La diferencia la resuelve B51.
test('it plans an order with the stored six-decimal factor, like the order screen', function () {
    $liter = UnitOfMeasure::factory()->create(['to_liter_conversion' => 1]);
    $gallon = UnitOfMeasure::factory()->create(['to_liter_conversion' => 3.7854]);

    // 1 L por galón de producto × 1.000 galones, con la MP en galones: factor 0,264173 (redondeado) → 264,1730 gal. La
    // cuenta exacta da 264,1729 (convertQuantity).
    expect(app(FormulaService::class)->orderLineRequirement(formulaLine($liter, $gallon, '1'), '1000'))
        ->toBe(['planned_quantity' => '264.1730', 'conversion_factor' => '0.264173'])
        ->and(app(FormulaService::class)->convertQuantity('1000', $liter, $gallon, 4))->toBe('264.1729');
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
