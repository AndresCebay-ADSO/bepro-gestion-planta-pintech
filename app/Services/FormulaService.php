<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NumberType;
use App\Models\ProductionOrder;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Collection;

class FormulaService
{
    public function __construct(
        private readonly DecimalCalculator $calculator
    ) {}

    /**
     * Calcula las cantidades planificadas de materia prima para una orden de producción.
     *
     * @return Collection Colección de arrays con [raw_material_id => planned_quantity]
     */
    public function calculatePlannedMaterials(ProductionOrder $order): Collection
    {
        $order->loadMissing(['formula.details.unitOfMeasure', 'formula.details.rawMaterial.unitOfMeasure']);
        $formula = $order->formula;
        $baseQuantity = (string) $order->quantity; // Cantidad total a producir en la unidad base del producto

        $materials = collect();

        foreach ($formula->details as $detail) {
            // La línea va en su propia unidad: se pasa a la de la materia prima y se multiplica por la cantidad a
            // producir, con 10 decimales; solo el resultado se redondea a los 4 de la base.
            $quantityPerUnit = $this->convertQuantity((string) $detail->quantity, $detail->unitOfMeasure, $detail->rawMaterial->unitOfMeasure);
            $plannedQuantity = $this->calculator->round($this->calculator->mul($quantityPerUnit, $baseQuantity, 10), 4);

            $materials->push([
                'raw_material_id' => $detail->raw_material_id,
                'planned_quantity' => (float) $plannedQuantity,
                'unit_of_measure_id' => $detail->rawMaterial?->unit_of_measure_id, // Mantenemos la unidad base de la materia prima
            ]);
        }

        return $materials;
    }

    /**
     * Pasa una cantidad de una unidad a otra por su equivalencia a litros o a kilos: cantidad × equivalencia de origen ÷
     * equivalencia de destino, con `$scale` decimales y sin pasar por un factor intermedio redondeado (con 6 decimales,
     * mL → galón perdía un 0,065 %). Es la única conversión de cantidades del sistema: la usan el costeo de la fórmula, el
     * plan de la OP y la validación de stock.
     *
     * @throws \DomainException si no hay equivalencia común (peso con volumen, o una unidad sin equivalencia).
     */
    public function convertQuantity(string|int|float $quantity, UnitOfMeasure $fromUnit, UnitOfMeasure $toUnit, int $scale = 10): string
    {
        if ($fromUnit->id === $toUnit->id) {
            return $this->calculator->mul($quantity, '1', $scale);
        }

        [$fromEquivalence, $toEquivalence] = $this->commonEquivalence($fromUnit, $toUnit) ?? throw new \DomainException(
            "No se puede convertir entre '{$fromUnit->symbol}' y '{$toUnit->symbol}': "
            .'no hay factores de conversión compatibles (ni por peso ni por volumen).'
        );

        return $this->calculator->div(
            $this->calculator->mul($quantity, $fromEquivalence, $scale + NumberType::Factor->scale()),
            $toEquivalence,
            $scale
        );
    }

    /**
     * El factor entre dos unidades, con los 6 decimales de su columna. Solo para guardarlo en la línea de la OP
     * (`production_order_details.conversion_factor`), que la pantalla usa para pasar el consumo real a la unidad de la
     * materia prima (B51). Las cantidades no se calculan con él, sino con convertQuantity(), que no lo redondea.
     *
     * @throws \DomainException si no hay equivalencia común.
     */
    public function getConversionFactor(UnitOfMeasure $fromUnit, UnitOfMeasure $toUnit): string
    {
        return $this->convertQuantity('1', $fromUnit, $toUnit, NumberType::Factor->scale());
    }

    public function canConvert(UnitOfMeasure $fromUnit, UnitOfMeasure $toUnit): bool
    {
        return $fromUnit->id === $toUnit->id || $this->commonEquivalence($fromUnit, $toUnit) !== null;
    }

    /**
     * Las equivalencias que comparten las dos unidades: a litros si ambas la tienen; si no, a kilos. Una unidad tiene
     * solo una de las dos (StoreUnitOfMeasureRequest).
     *
     * @return array{0: string, 1: string}|null
     */
    private function commonEquivalence(UnitOfMeasure $fromUnit, UnitOfMeasure $toUnit): ?array
    {
        if ($fromUnit->to_liter_conversion !== null && $toUnit->to_liter_conversion !== null) {
            return [(string) $fromUnit->to_liter_conversion, (string) $toUnit->to_liter_conversion];
        }

        if ($fromUnit->to_kg_conversion !== null && $toUnit->to_kg_conversion !== null) {
            return [(string) $fromUnit->to_kg_conversion, (string) $toUnit->to_kg_conversion];
        }

        return null;
    }
}
