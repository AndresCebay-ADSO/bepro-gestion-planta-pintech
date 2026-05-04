<?php

declare(strict_types=1);

namespace App\Concerns;

trait DeterminesPriceRefresh
{
    private function shouldUpdatePriceFromCostChange(?float $currentPrice, ?float $previousCost, float $newCost, float $threshold): bool
    {
        if ($currentPrice === null) {
            return true;
        }

        if ($previousCost === null || $previousCost <= 0) {
            return false;
        }

        $variationPercentage = abs((($newCost - $previousCost) / $previousCost) * 100);

        return $variationPercentage >= $threshold;
    }
}
