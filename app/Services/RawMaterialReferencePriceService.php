<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InventoryBatch;
use App\Models\RawMaterial;
use Illuminate\Support\Facades\DB;

class RawMaterialReferencePriceService
{
    public function syncRawMaterialCurrentPrice(int $rawMaterialId): bool
    {
        return DB::transaction(function () use ($rawMaterialId): bool {
            $rawMaterial = RawMaterial::query()
                ->select(['id', 'current_price', 'previous_price'])
                ->lockForUpdate()
                ->find($rawMaterialId);

            if ($rawMaterial === null) {
                return false;
            }

            $currentPrice = $rawMaterial->current_price !== null ? (float) $rawMaterial->current_price : null;
            $referencePrice = $this->calculateReferencePrice($rawMaterialId, $currentPrice);

            if ($referencePrice === null) {
                return false;
            }

            if ($this->pricesAreEqual($currentPrice, $referencePrice)) {
                return false;
            }

            $rawMaterial->update([
                'previous_price' => $rawMaterial->current_price,
                'current_price' => $referencePrice,
            ]);

            return true;
        }, attempts: 3);
    }

    public function calculateReferencePrice(int $rawMaterialId, ?float $currentPrice = null): ?float
    {
        $latestLotPrice = InventoryBatch::query()
            ->where('raw_material_id', $rawMaterialId)
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->value('unit_price');
        $availableStats = InventoryBatch::query()
            ->where('raw_material_id', $rawMaterialId)
            ->where('remaining_quantity', '>', 0)
            ->selectRaw('
                SUM(remaining_quantity * unit_price) as weighted_total,
                SUM(remaining_quantity) as total_quantity,
                MAX(unit_price) as highest_unit_price
            ')
            ->first();

        $weightedAveragePrice = null;
        if ($availableStats !== null && (float) $availableStats->total_quantity > 0) {
            $weightedAveragePrice = (float) $availableStats->weighted_total / (float) $availableStats->total_quantity;
        }

        $highestAvailableLotPrice = $availableStats?->highest_unit_price;

        $policy = (string) config('production.raw_material_reference_price_policy', 'conservative_max');

        $referencePrice = match ($policy) {
            'last_lot' => $this->firstAvailableNumericValue([$latestLotPrice, $weightedAveragePrice, $currentPrice]),
            'weighted_average' => $this->firstAvailableNumericValue([$weightedAveragePrice, $latestLotPrice, $currentPrice]),
            default => $this->firstAvailableNumericValue([$highestAvailableLotPrice, $currentPrice, $latestLotPrice]),
        };

        return $referencePrice !== null ? round($referencePrice, 4) : null;
    }

    /**
     * @param  array<int, float|int|string|null>  $values
     */
    private function firstAvailableNumericValue(array $values): ?float
    {
        foreach ($values as $value) {
            if ($value !== null && is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    private function pricesAreEqual(?float $priceA, ?float $priceB): bool
    {
        if ($priceA === null || $priceB === null) {
            return $priceA === $priceB;
        }

        return number_format($priceA, 4, '.', '') === number_format($priceB, 4, '.', '');
    }
}
