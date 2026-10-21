<?php

declare(strict_types=1);

use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\Product;
use App\Models\ProductionCost;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Services\ProductionCostRecalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

/*
 * El costo de la fórmula convierte la unidad de cada línea a la de su materia prima, igual que la OP (auditoría 3.6,
 * FOR-06), y sale de la fórmula de referencia, no de una activa cualquiera (FOR-01).
 */

beforeEach(function () {
    $this->kg = UnitOfMeasure::factory()->create(['code' => 'kg', 'symbol' => 'kg', 'to_kg_conversion' => 1]);
    $this->gram = UnitOfMeasure::factory()->create(['code' => 'g', 'symbol' => 'g', 'to_kg_conversion' => 0.001]);
    $this->liter = UnitOfMeasure::factory()->create(['code' => 'l', 'symbol' => 'L', 'to_liter_conversion' => 1]);
    $this->gallon = UnitOfMeasure::factory()->create(['code' => 'gal', 'symbol' => 'gal', 'to_liter_conversion' => 3.7854]);

    $this->resin = RawMaterial::factory()->create(['unit_of_measure_id' => $this->kg->id, 'current_price' => 10000]);
    $this->solvent = RawMaterial::factory()->create(['unit_of_measure_id' => $this->liter->id, 'current_price' => 1000]);

    $this->product = Product::factory()->create(['current_cost' => null]);

    $this->formulaWith = function (array $lines, array $attributes = []): Formula {
        $formula = Formula::factory()->for($this->product)->create($attributes);

        foreach ($lines as $step => [$material, $quantity, $unit]) {
            FormulaDetail::create([
                'formula_id' => $formula->id,
                'raw_material_id' => $material->id,
                'quantity' => $quantity,
                'unit_of_measure_id' => $unit->id,
                'step_order' => $step + 1,
            ]);
        }

        return $formula;
    };

    $this->recalculate = fn (): ?ProductionCost => app(ProductionCostRecalculationService::class)
        ->recalculateForProduct((int) $this->product->id);
});

it('costea una línea en gramos de una materia prima que se compra por kg', function () {
    // 500 g a $10.000 el kg = $5.000 (sin convertir daba $5.000.000).
    ($this->formulaWith)([[$this->resin, '500', $this->gram]]);

    expect(($this->recalculate)()->cost)->toBe('5000.0000')
        ->and($this->product->fresh()->current_cost)->toBe('5000.0000');
});

it('costea una línea en galones de una materia prima que se compra por litro', function () {
    // 1 gal = 3,7854 L a $1.000 el litro.
    ($this->formulaWith)([[$this->solvent, '1', $this->gallon]]);

    expect(($this->recalculate)()->cost)->toBe('3785.4000');
});

it('costea mililitros de una materia prima que se compra por galón sin perder decimales', function () {
    $milliliter = UnitOfMeasure::factory()->create(['code' => 'ml', 'symbol' => 'mL', 'to_liter_conversion' => 0.001]);
    $tinter = RawMaterial::factory()->create(['unit_of_measure_id' => $this->gallon->id, 'current_price' => 100000]);

    // 500 mL = 0,1320864373 gal a $100.000. Con el factor cortado a 6 decimales daba $13.200,0000.
    ($this->formulaWith)([[$tinter, '500', $milliliter]]);

    expect(($this->recalculate)()->cost)->toBe('13208.6437');
});

it('no cambia el costo cuando la línea ya está en la unidad de la materia prima', function () {
    ($this->formulaWith)([[$this->resin, '0.3142', $this->kg], [$this->solvent, '0.5', $this->liter]]);

    // 0,3142 × 10.000 + 0,5 × 1.000
    expect(($this->recalculate)()->cost)->toBe('3642.0000');
});

it('costea con la fórmula de referencia aunque haya otras activas', function () {
    $reference = ($this->formulaWith)([[$this->resin, '1', $this->kg]]);
    ($this->formulaWith)([[$this->resin, '2', $this->kg]]);

    $cost = ($this->recalculate)();

    expect($cost->formula_id)->toBe($reference->id)
        ->and($cost->cost)->toBe('10000.0000');
});

it('no costea un producto cuyas fórmulas están todas inactivas', function () {
    ($this->formulaWith)([[$this->resin, '1', $this->kg]], ['is_active' => false]);

    expect(($this->recalculate)())->toBeNull();
});

it('al cambiar el precio de una materia prima recalcula solo los productos cuya referencia la usa', function () {
    ($this->formulaWith)([[$this->resin, '1', $this->kg]]);
    // El solvente solo está en una fórmula activa que no es la de referencia.
    ($this->formulaWith)([[$this->solvent, '1', $this->liter]]);

    $service = app(ProductionCostRecalculationService::class);

    expect($service->recalculateForRawMaterial((int) $this->solvent->id))->toBe(0)
        ->and(ProductionCost::query()->where('product_id', $this->product->id)->count())->toBe(0)
        ->and($service->recalculateForRawMaterial((int) $this->resin->id))->toBe(1);
});

it('al corregir la equivalencia de una unidad recalcula solo los productos cuya referencia la usa', function () {
    // 500 g a $10.000/kg = $5.000. Si el gramo pasara a valer 0,002 kg (corrección), el costo sería $10.000.
    ($this->formulaWith)([[$this->resin, '500', $this->gram]]);
    ($this->recalculate)();

    $other = Product::factory()->create(['current_cost' => null]);
    $otherFormula = Formula::factory()->for($other)->create();
    FormulaDetail::create(['formula_id' => $otherFormula->id, 'raw_material_id' => $this->solvent->id, 'quantity' => 1, 'unit_of_measure_id' => $this->liter->id, 'step_order' => 1]);

    $this->gram->update(['to_kg_conversion' => 0.002]);

    expect(app(ProductionCostRecalculationService::class)->recalculateForUnit((int) $this->gram->id))->toBe(1)
        ->and($this->product->fresh()->current_cost)->toBe('10000.0000')
        ->and($other->fresh()->current_cost)->toBeNull();
});

// Un producto que no se puede costear (línea cargada sin validar, P-5) no frena el recorrido de los demás: queda en el
// log (CodeRabbit y revisión final del PR 9; CL-07).
it('un producto que no se puede costear no frena el recálculo de los demás y queda en el log', function () {
    Log::spy();
    ($this->formulaWith)([[$this->resin, '1', $this->kg]]);

    $broken = Product::factory()->create(['current_cost' => null]);
    $brokenFormula = Formula::factory()->for($broken)->create();
    // kg de un solvente que se compra por litro: la aplicación ya no deja guardarlo, solo llega por una carga directa.
    FormulaDetail::create(['formula_id' => $brokenFormula->id, 'raw_material_id' => $this->resin->id, 'quantity' => 1, 'unit_of_measure_id' => $this->kg->id, 'step_order' => 1]);
    FormulaDetail::create(['formula_id' => $brokenFormula->id, 'raw_material_id' => $this->solvent->id, 'quantity' => 1, 'unit_of_measure_id' => $this->kg->id, 'step_order' => 2]);

    expect(app(ProductionCostRecalculationService::class)->recalculateForRawMaterial((int) $this->resin->id))->toBe(1)
        ->and($this->product->fresh()->current_cost)->toBe('10000.0000')
        ->and($broken->fresh()->current_cost)->toBeNull();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $context['product_id'] === $broken->id && $context['raw_material_id'] === $this->resin->id)
        ->once();
});
