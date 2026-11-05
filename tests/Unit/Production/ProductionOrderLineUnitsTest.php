<?php

declare(strict_types=1);

use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Services\Production\ProductionOrderLineUnits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->lineUnits = app(ProductionOrderLineUnits::class);
});

test('it rounds to the decimals of the column when converting to the raw material unit', function () {
    expect($this->lineUnits->toMaterialUnit('12.35', '0.001000'))->toBe('0.0124');
});

test('without a factor it only normalizes to the decimals of the column', function () {
    expect($this->lineUnits->toMaterialUnit('0.17', null))->toBe('0.1700')
        ->and($this->lineUnits->toMaterialUnit(3.0, null))->toBe('3.0000');
});

test('a quantity that does not fit once converted is a validation error on the ingredients', function () {
    try {
        $this->lineUnits->toMaterialUnit('100000000', '1000.000000');
        $this->fail('Debió rechazar una cantidad que no cabe en la columna.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('ingredients');
    }
});

test('it shows the stored quantity in the unit the line is typed in', function () {
    expect($this->lineUnits->toDisplayUnit('0.0375', '0.001000'))->toBe('37.5000')
        ->and($this->lineUnits->toDisplayUnit('2.5000', null))->toBe('2.5000');
});

test('a factor of zero counts as no factor, so nothing divides by it', function () {
    $material = RawMaterial::factory()->create();
    $formula = Formula::factory()->create();
    FormulaDetail::create([
        'formula_id' => $formula->id,
        'raw_material_id' => $material->id,
        'quantity' => 1,
        'unit_of_measure_id' => UnitOfMeasure::factory()->create()->id,
        'step_order' => 1,
    ]);
    $order = ProductionOrder::factory()->create(['formula_id' => $formula->id, 'product_id' => $formula->product_id]);
    $detail = ProductionOrderDetail::create([
        'production_order_id' => $order->id,
        'raw_material_id' => $material->id,
        'step_order' => 1,
        'planned_quantity' => 1,
        'conversion_factor' => 0,
        'unit_cost' => 0,
        'total_cost' => 0,
    ]);

    expect($this->lineUnits->conversionFactors($order)[$detail->id])->toBeNull();
});

test('a line that does not belong to the order is left as it came', function () {
    $order = ProductionOrder::factory()->create();

    $rows = [['id' => 999999, 'actual_quantity' => '37.5']];

    expect($this->lineUnits->ingredientsInMaterialUnit($order, $rows))->toBe($rows);
});
