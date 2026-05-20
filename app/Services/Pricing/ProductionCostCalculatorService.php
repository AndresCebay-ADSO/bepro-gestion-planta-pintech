<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Models\ProductionOrder;

class ProductionCostCalculatorService
{
    /**
     * @param  array<int, array{id:int,actual_units:float|int}>  $packagingData
     * @return array<int, float>
     */
    public function calculateDistributedBulkCosts(ProductionOrder $order, array $packagingData, float $totalBulkCost): array
    {
        if ($packagingData === [] || $totalBulkCost <= 0) {
            return [];
        }

        $packagingDataByPlanId = [];

        foreach ($packagingData as $packData) {
            $packagingDataByPlanId[(int) $packData['id']] = $packData;
        }

        $order->loadMissing('packagingPlans.productVariant');
        $plans = $order->packagingPlans;

        $totalEquivalentYield = 0.0;

        foreach ($plans as $plan) {
            if (! isset($packagingDataByPlanId[$plan->id])) {
                continue;
            }

            $variant = $plan->productVariant;
            if ($variant === null) {
                continue;
            }

            $actualUnits = (float) ($packagingDataByPlanId[$plan->id]['actual_units'] ?? 0);
            if ($actualUnits <= 0) {
                continue;
            }

            $presentationValue = (float) ($variant->presentation_value ?? 1);
            $totalEquivalentYield += $actualUnits * $presentationValue;
        }

        if ($totalEquivalentYield <= 0) {
            return [];
        }

        $bulkCostPerEquivalentUnit = $totalBulkCost / $totalEquivalentYield;
        $distributedCosts = [];

        foreach ($plans as $plan) {
            if (! isset($packagingDataByPlanId[$plan->id])) {
                continue;
            }

            $variant = $plan->productVariant;
            if ($variant === null) {
                continue;
            }

            $actualUnits = (float) ($packagingDataByPlanId[$plan->id]['actual_units'] ?? 0);
            if ($actualUnits <= 0) {
                continue;
            }

            $presentationValue = (float) ($variant->presentation_value ?? 1);
            $distributedCosts[(int) $variant->id] = $bulkCostPerEquivalentUnit * $presentationValue;
        }

        return $distributedCosts;
    }
}
