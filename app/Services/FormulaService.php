<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NumberType;
use App\Models\FormulaDetail;
use App\Models\UnitOfMeasure;

class FormulaService
{
    public function __construct(
        private readonly DecimalCalculator $calculator
    ) {}

    /**
     * Lo que pide una línea de fórmula para una OP: la cantidad de materia prima, en la unidad de la materia prima, y el
     * factor con que se calcula, que la OP guarda en su línea. Es la única cuenta del plan de la OP: la usan crear la OP y
     * la validación de stock.
     *
     * La OP calcula con su factor guardado (6 decimales), no con convertQuantity(): la pantalla convierte el consumo real
     * con ese mismo factor, y así plan y consumo cuadran, y editar una unidad no cambia las OP ya creadas. Con L↔galón la
     * diferencia con el costo de lista (exacto) es de un 0,0002 %. Lo resuelve B51, antes de producción: el servidor
     * convierte el consumo y la OP guarda su equivalencia con más precisión.
     *
     * @return array{planned_quantity: string, conversion_factor: string}
     *
     * @throws \DomainException si la unidad de la línea no se convierte a la de la materia prima.
     */
    public function orderLineRequirement(FormulaDetail $detail, string|int|float $orderQuantity): array
    {
        $factor = $this->getConversionFactor($detail->unitOfMeasure, $detail->rawMaterial->unitOfMeasure);
        $perUnit = $this->calculator->mul((string) $detail->quantity, $factor, 10);

        return [
            'planned_quantity' => $this->calculator->round($this->calculator->mul($perUnit, $orderQuantity, 10), 4),
            'conversion_factor' => $factor,
        ];
    }

    /**
     * Pasa una cantidad de una unidad a otra por su equivalencia a litros o a kilos: cantidad × equivalencia de origen ÷
     * equivalencia de destino, con `$scale` decimales y sin pasar por un factor intermedio redondeado (con 6 decimales,
     * mL → galón perdía un 0,065 %). La usan el costeo de la fórmula y la validación de unidades (FOR-02); el plan de la
     * OP calcula con el factor que guarda (orderLineRequirement()).
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
     * materia prima (B51), y con el que la OP calcula su plan (orderLineRequirement()). El costo de lista no lo usa.
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
