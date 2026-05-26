<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProductionOrder;
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
        $formula = $order->formula;
        $baseQuantity = (string) $order->quantity; // Cantidad total a producir en la unidad base del producto

        // Obtenemos la unidad base del producto (kg, L, etc.)
        $productBaseUnit = $order->product->unitOfMeasure;

        $materials = collect();

        foreach ($formula->details as $detail) {
            // La cantidad en la fórmula está expresada en la unidad del detalle
            // Necesitamos convertir a la unidad base si son diferentes
            $factor = $this->getConversionFactor($detail->unitOfMeasure, $productBaseUnit);

            $detailQty = (string) $detail->quantity;

            // Calculamos con 10 decimales para evitar errores de truncamiento intermedio
            $innerCalc = $this->calculator->mul($detailQty, $baseQuantity, 10);
            $fullPrecisionResult = $this->calculator->mul($innerCalc, $factor, 10);

            // Solo al final redondeamos el resultado a los 4 decimales de la base de datos
            $plannedQuantity = $this->calculator->round($fullPrecisionResult, 4);

            $materials->push([
                'raw_material_id' => $detail->raw_material_id,
                'planned_quantity' => (float) $plannedQuantity,
                'unit_of_measure_id' => $detail->unit_of_measure_id, // Mantenemos la unidad del detalle
            ]);
        }

        return $materials;
    }

    /**
     * Obtiene el factor de conversión entre dos unidades de medida.
     * Utiliza las equivalencias a KG o Litros definidas en la base de datos.
     */
    protected function getConversionFactor($fromUnit, $toUnit): string
    {
        if ($fromUnit->id === $toUnit->id) {
            return '1.0000';
        }

        // Conversión basada en Volumen (Litros)
        if ($fromUnit->to_liter_conversion !== null && $toUnit->to_liter_conversion !== null) {
            return $this->calculator->div(
                (string) $fromUnit->to_liter_conversion,
                (string) $toUnit->to_liter_conversion,
                4
            );
        }

        // Conversión basada en Peso (KG)
        if ($fromUnit->to_kg_conversion !== null && $toUnit->to_kg_conversion !== null) {
            return $this->calculator->div(
                (string) $fromUnit->to_kg_conversion,
                (string) $toUnit->to_kg_conversion,
                4
            );
        }

        // No hay factores de conversión compatibles entre las unidades
        throw new \DomainException(
            "No se puede convertir entre '{$fromUnit->symbol}' y '{$toUnit->symbol}': "
            .'no hay factores de conversión compatibles (ni por peso ni por volumen).'
        );
    }
}
