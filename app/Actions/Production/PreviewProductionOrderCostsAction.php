<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Models\ProductionOrder;
use App\Services\Inventory\FifoStockAllocatorService;
use App\Services\Pricing\ProductionCostCalculatorService;

class PreviewProductionOrderCostsAction
{
    public function __construct(
        private readonly FifoStockAllocatorService $fifoStockAllocator,
        private readonly ProductionCostCalculatorService $productionCostCalculator
    ) {}

    /**
     * @param  array<int, array{id:int,actual_quantity:float|int}>  $ingredients
     * @param  array<int, array{id:int,actual_units:float|int}>  $packaging
     * @return array{
     *   ingredients: array<int, array{id:int,unit_cost:float,total_cost:float,actual_quantity:float}>,
     *   packaging: array<int, array{id:int,cost_price:float,total_cost:float,equivalent:float,actual_units:float}>,
     *   total_bulk_cost: float,
     *   total_finished_cost: float,
     *   total_equivalent: float
     * }
     */
    public function execute(ProductionOrder $order, array $ingredients, array $packaging): array
    {
        $order->loadMissing(['details', 'packagingPlans.productVariant']);

        $detailsById = $order->details->keyBy('id');
        $ingredientRequirements = [];
        $ingredientRows = [];
        $totalBulkCost = 0.0;

        foreach ($ingredients as $ingredientData) {
            $detailId = (int) ($ingredientData['id'] ?? 0);
            $actualQuantity = max(0.0, (float) ($ingredientData['actual_quantity'] ?? 0));
            $detail = $detailsById->get($detailId);

            if ($detail === null) {
                continue;
            }

            $ingredientRequirements[(int) $detail->raw_material_id] =
                ($ingredientRequirements[(int) $detail->raw_material_id] ?? 0.0) + $actualQuantity;

            $ingredientRows[] = [
                'id' => $detailId,
                'raw_material_id' => (int) $detail->raw_material_id,
                'actual_quantity' => $actualQuantity,
            ];
        }

        $ingredientUnitCosts = $this->fifoStockAllocator->estimateMaterialUnitCostsForPlanning(
            warehouseId: (int) $order->warehouse_id,
            requirementsByMaterialId: $ingredientRequirements
        );

        $ingredientResults = [];

        foreach ($ingredientRows as $row) {
            $unitCost = (float) ($ingredientUnitCosts[$row['raw_material_id']] ?? 0.0);
            $totalCost = $row['actual_quantity'] * $unitCost;
            $totalBulkCost += $totalCost;

            $ingredientResults[] = [
                'id' => $row['id'],
                'actual_quantity' => $row['actual_quantity'],
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
            ];
        }

        $order->loadMissing('lineAdjustments');
        $adjustmentRequirements = [];

        foreach ($order->lineAdjustments as $adjustment) {
            $materialId = (int) $adjustment->raw_material_id;
            $adjustmentRequirements[$materialId] = ($adjustmentRequirements[$materialId] ?? 0.0)
                + (float) $adjustment->quantity;
        }

        if ($adjustmentRequirements !== []) {
            $adjustmentUnitCosts = $this->fifoStockAllocator->estimateMaterialUnitCostsForPlanning(
                warehouseId: (int) $order->warehouse_id,
                requirementsByMaterialId: $adjustmentRequirements
            );

            foreach ($adjustmentRequirements as $materialId => $quantity) {
                $unitCost = (float) ($adjustmentUnitCosts[$materialId] ?? 0.0);
                $totalBulkCost += $quantity * $unitCost;
            }
        }

        $distributedBulkCosts = $this->productionCostCalculator->calculateDistributedBulkCosts(
            order: $order,
            packagingData: $packaging,
            totalBulkCost: $totalBulkCost
        );

        $plansById = $order->packagingPlans->keyBy('id');
        $packagingRequirements = [];
        $packagingRows = [];

        foreach ($packaging as $packagingData) {
            $planId = (int) ($packagingData['id'] ?? 0);
            $actualUnits = max(0.0, (float) ($packagingData['actual_units'] ?? 0));
            $plan = $plansById->get($planId);

            if ($plan === null || $actualUnits <= 0) {
                continue;
            }

            $packageRawMaterialId = $plan->productVariant?->package_raw_material_id;
            if ($packageRawMaterialId !== null) {
                $packagingRequirements[(int) $packageRawMaterialId] =
                    ($packagingRequirements[(int) $packageRawMaterialId] ?? 0.0) + $actualUnits;
            }

            $packagingRows[] = [
                'id' => $planId,
                'actual_units' => $actualUnits,
                'product_variant_id' => (int) $plan->product_variant_id,
                'presentation_value' => (float) ($plan->productVariant?->presentation_value ?? 1),
                'package_raw_material_id' => $packageRawMaterialId !== null ? (int) $packageRawMaterialId : null,
            ];
        }

        $packagingUnitCosts = $this->fifoStockAllocator->estimateMaterialUnitCostsForPlanning(
            warehouseId: (int) $order->warehouse_id,
            requirementsByMaterialId: $packagingRequirements
        );

        $packagingResults = [];
        $totalFinishedCost = 0.0;
        $totalEquivalent = 0.0;

        foreach ($packagingRows as $row) {
            $bulkCostPerUnit = (float) ($distributedBulkCosts[$row['product_variant_id']] ?? 0.0);
            $packagingUnitCost = $row['package_raw_material_id'] !== null
                ? (float) ($packagingUnitCosts[$row['package_raw_material_id']] ?? 0.0)
                : 0.0;

            $costPrice = $bulkCostPerUnit + $packagingUnitCost;
            $totalCost = $row['actual_units'] * $costPrice;
            $equivalent = $row['actual_units'] * $row['presentation_value'];

            $totalFinishedCost += $totalCost;
            $totalEquivalent += $equivalent;

            $packagingResults[] = [
                'id' => $row['id'],
                'actual_units' => $row['actual_units'],
                'cost_price' => $costPrice,
                'total_cost' => $totalCost,
                'equivalent' => $equivalent,
            ];
        }

        return [
            'ingredients' => $ingredientResults,
            'packaging' => $packagingResults,
            'total_bulk_cost' => $totalBulkCost,
            'total_finished_cost' => $totalFinishedCost,
            'total_equivalent' => $totalEquivalent,
        ];
    }
}
