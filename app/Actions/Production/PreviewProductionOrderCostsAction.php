<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Models\ProductionOrder;
use App\Services\DecimalCalculator;
use App\Services\Inventory\FifoStockAllocatorService;
use App\Services\Pricing\ProductionCostCalculatorService;

class PreviewProductionOrderCostsAction
{
    public function __construct(
        private readonly FifoStockAllocatorService $fifoStockAllocator,
        private readonly ProductionCostCalculatorService $productionCostCalculator,
        private readonly DecimalCalculator $calculator
    ) {}

    /**
     * @param  array<int, array{id:int,actual_quantity:float|int}>  $ingredients
     * @param  array<int, array{id:int,actual_units:float|int}>  $packaging
     * @return array{
     *   ingredients: array<int, array{id:int,unit_cost:string,total_cost:string,actual_quantity:float}>,
     *   packaging: array<int, array{id:int,cost_price:string,total_cost:string,equivalent:string,actual_units:float}>,
     *   total_bulk_cost: string,
     *   total_finished_cost: string,
     *   total_equivalent: string
     * }
     */
    public function execute(ProductionOrder $order, array $ingredients, array $packaging): array
    {
        $order->loadMissing(['details', 'packagingPlans.productVariant']);

        $detailsById = $order->details->keyBy('id');
        $ingredientRequirements = [];
        $ingredientRows = [];
        $totalBulkCost = '0';

        foreach ($ingredients as $ingredientData) {
            $detailId = (int) ($ingredientData['id'] ?? 0);
            $actualQuantity = max(0.0, (float) ($ingredientData['actual_quantity'] ?? 0));
            $detail = $detailsById->get($detailId);

            if ($detail === null) {
                continue;
            }

            $ingredientMaterialId = (int) $detail->raw_material_id;
            $ingredientRequirements[$ingredientMaterialId] = $this->calculator->add(
                $ingredientRequirements[$ingredientMaterialId] ?? '0',
                (string) $actualQuantity,
                4
            );

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
            $unitCost = (string) ($ingredientUnitCosts[$row['raw_material_id']] ?? '0');
            $totalCostStr = $this->calculator->mul((string) $row['actual_quantity'], $unitCost, 4);
            $totalBulkCost = $this->calculator->add($totalBulkCost, $totalCostStr, 4);

            $ingredientResults[] = [
                'id' => $row['id'],
                'actual_quantity' => $row['actual_quantity'],
                'unit_cost' => $unitCost,
                'total_cost' => $totalCostStr,
            ];
        }

        $order->loadMissing('lineAdjustments');
        $adjustmentRequirements = [];

        foreach ($order->lineAdjustments as $adjustment) {
            $adjustmentMaterialId = (int) $adjustment->raw_material_id;
            $adjustmentRequirements[$adjustmentMaterialId] = $this->calculator->add(
                $adjustmentRequirements[$adjustmentMaterialId] ?? '0',
                (string) $adjustment->quantity,
                4
            );
        }

        if ($adjustmentRequirements !== []) {
            $adjustmentUnitCosts = $this->fifoStockAllocator->estimateMaterialUnitCostsForPlanning(
                warehouseId: (int) $order->warehouse_id,
                requirementsByMaterialId: $adjustmentRequirements
            );

            foreach ($adjustmentRequirements as $materialId => $quantity) {
                $unitCost = (string) ($adjustmentUnitCosts[$materialId] ?? '0');
                $adjustmentCostStr = $this->calculator->mul((string) $quantity, $unitCost, 4);
                $totalBulkCost = $this->calculator->add($totalBulkCost, $adjustmentCostStr, 4);
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
                $pkgMaterialId = (int) $packageRawMaterialId;
                $packagingRequirements[$pkgMaterialId] = $this->calculator->add(
                    $packagingRequirements[$pkgMaterialId] ?? '0',
                    (string) $actualUnits,
                    4
                );
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
        $totalFinishedCost = '0';
        $totalEquivalent = '0';

        foreach ($packagingRows as $row) {
            $bulkCostPerUnit = (string) ($distributedBulkCosts[$row['product_variant_id']] ?? '0');
            $packagingUnitCost = $row['package_raw_material_id'] !== null
                ? (string) ($packagingUnitCosts[$row['package_raw_material_id']] ?? '0')
                : '0';

            $costPrice = $this->calculator->add($bulkCostPerUnit, $packagingUnitCost, 4);
            $totalCostStr = $this->calculator->mul((string) $row['actual_units'], $costPrice, 4);
            $equivalentStr = $this->calculator->mul((string) $row['actual_units'], (string) $row['presentation_value'], 4);

            $totalFinishedCost = $this->calculator->add($totalFinishedCost, $totalCostStr, 4);
            $totalEquivalent = $this->calculator->add($totalEquivalent, $equivalentStr, 4);

            $packagingResults[] = [
                'id' => $row['id'],
                'actual_units' => $row['actual_units'],
                'cost_price' => $costPrice,
                'total_cost' => $totalCostStr,
                'equivalent' => $equivalentStr,
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
