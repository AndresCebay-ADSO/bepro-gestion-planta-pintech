<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Models\ProductionOrder;
use App\Services\DecimalCalculator;

class ProductionCostCalculatorService
{
    public function __construct(
        private readonly DecimalCalculator $calculator
    ) {}

    /**
     * @param  array<int, array{id:int,actual_units:float|int}>  $packagingData
     * @return array<int, string>
     */
    public function calculateDistributedBulkCosts(ProductionOrder $order, array $packagingData, string|float $totalBulkCost): array
    {
        if ($packagingData === [] || $this->calculator->isZero($totalBulkCost)) {
            return [];
        }

        $totalBulkCostStr = (string) $totalBulkCost;
        $packagingDataByPlanId = [];

        foreach ($packagingData as $packData) {
            $packagingDataByPlanId[(int) $packData['id']] = $packData;
        }

        $order->loadMissing('packagingPlans.productVariant');
        $plans = $order->packagingPlans;

        $totalEquivalentYield = '0';

        foreach ($plans as $plan) {
            if (! isset($packagingDataByPlanId[$plan->id])) {
                continue;
            }

            $variant = $plan->productVariant;
            if ($variant === null) {
                continue;
            }

            $actualUnits = (string) ($packagingDataByPlanId[$plan->id]['actual_units'] ?? 0);
            if ($this->calculator->isZero($actualUnits)) {
                continue;
            }

            $presentationValue = (string) ($variant->presentation_value ?? 1);
            $yieldFromVariant = $this->calculator->mul($actualUnits, $presentationValue, 4);
            $totalEquivalentYield = $this->calculator->add($totalEquivalentYield, $yieldFromVariant, 4);
        }

        if ($this->calculator->isZero($totalEquivalentYield)) {
            return [];
        }

        $bulkCostPerEquivalentUnit = $this->calculator->div($totalBulkCostStr, $totalEquivalentYield, 4);
        $distributedCosts = [];

        foreach ($plans as $plan) {
            if (! isset($packagingDataByPlanId[$plan->id])) {
                continue;
            }

            $variant = $plan->productVariant;
            if ($variant === null) {
                continue;
            }

            $actualUnits = (string) ($packagingDataByPlanId[$plan->id]['actual_units'] ?? 0);
            if ($this->calculator->isZero($actualUnits)) {
                continue;
            }

            $presentationValue = (string) ($variant->presentation_value ?? 1);
            $distributedCost = $this->calculator->mul($bulkCostPerEquivalentUnit, $presentationValue, 4);
            $distributedCosts[(int) $variant->id] = $distributedCost;
        }

        return $distributedCosts;
    }
}
