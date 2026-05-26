<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Services\DecimalCalculator;

trait DeterminesPriceRefresh
{
    private function shouldUpdatePriceFromCostChange(?string $currentPrice, ?string $previousCost, string $newCost, string $threshold): bool
    {
        if ($currentPrice === null) {
            return true;
        }

        if ($previousCost === null) {
            return true;
        }

        $calculator = app(DecimalCalculator::class);

        if ($calculator->cmp($previousCost, '0', 4) <= 0) {
            return false;
        }

        $difference = $calculator->sub($newCost, $previousCost, 4);
        $ratio = $calculator->div($difference, $previousCost, 4);
        $variationPercentage = $calculator->abs($calculator->mul($ratio, '100', 4), 4);

        return $calculator->cmp($variationPercentage, $threshold, 4) >= 0;
    }
}
