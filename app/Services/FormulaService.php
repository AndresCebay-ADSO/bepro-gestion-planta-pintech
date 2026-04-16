<?php

namespace App\Services;

use App\Models\ProductionOrder;
use Illuminate\Support\Collection;

class FormulaService
{
    /**
     * Calcula las cantidades planificadas de materia prima para una orden de producción.
     *
     * @return Collection Colección de arrays con [raw_material_id => planned_quantity]
     */
    public function calculatePlannedMaterials(ProductionOrder $order): Collection
    {
        $formula = $order->formula;
        $baseQuantity = $order->quantity; // Cantidad total a producir en la unidad base del producto

        // Obtenemos la unidad base del producto (kg, L, etc.)
        $productBaseUnit = $order->product->unitOfMeasure;

        $materials = collect();

        foreach ($formula->details as $detail) {
            // La cantidad en la fórmula está expresada en la unidad del detalle
            // Necesitamos convertir a la unidad base si son diferentes
            $factor = $this->getConversionFactor($detail->unitOfMeasure, $productBaseUnit);

            $plannedQuantity = $detail->quantity * $baseQuantity * $factor;

            $materials->push([
                'raw_material_id' => $detail->raw_material_id,
                'planned_quantity' => $plannedQuantity,
                'unit_of_measure_id' => $detail->unit_of_measure_id, // Mantenemos la unidad del detalle
            ]);
        }

        return $materials;
    }

    /**
     * Obtiene el factor de conversión entre dos unidades de medida.
     * (Implementación simplificada, asume conversiones lineales definidas en la DB)
     */
    protected function getConversionFactor($fromUnit, $toUnit): float
    {
        if ($fromUnit->id === $toUnit->id) {
            return 1.0;
        }

        // Aquí iría la lógica usando los campos to_kg_conversion / to_liter_conversion
        // Por ahora, retornamos 1.0 como placeholder
        return 1.0;
    }
}
