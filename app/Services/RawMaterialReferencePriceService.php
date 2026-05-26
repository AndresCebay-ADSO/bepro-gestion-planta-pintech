<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InventoryBatch;
use App\Models\RawMaterial;
use Illuminate\Support\Facades\DB;

class RawMaterialReferencePriceService
{
    public function __construct(
        private readonly DecimalCalculator $calculator
    ) {}

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

            $currentPrice = $rawMaterial->current_price !== null ? (string) $rawMaterial->current_price : null;
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

    public function calculateReferencePrice(int $rawMaterialId, ?string $currentPrice = null): ?string
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
        if ($availableStats !== null && $availableStats->total_quantity !== null && ! $this->calculator->isZero((string) $availableStats->total_quantity)) {
            $weightedAveragePrice = $this->calculator->div(
                (string) $availableStats->weighted_total,
                (string) $availableStats->total_quantity,
                4
            );
        }

        $highestAvailableLotPrice = $availableStats?->highest_unit_price;

        $policy = (string) config('production.raw_material_reference_price_policy', 'conservative_max');

        $referencePrice = match ($policy) {
            'last_lot' => $this->firstAvailableNumericValue([$latestLotPrice, $weightedAveragePrice, $currentPrice]),
            'weighted_average' => $this->firstAvailableNumericValue([$weightedAveragePrice, $latestLotPrice, $currentPrice]),
            default => $this->firstAvailableNumericValue([$highestAvailableLotPrice, $currentPrice, $latestLotPrice]),
        };

        return $referencePrice !== null ? $this->calculator->round($referencePrice, 4) : null;
    }

    /**
     * @param  array<int, float|int|string|null>  $values
     */
    private function firstAvailableNumericValue(array $values): ?string
    {
        foreach ($values as $value) {
            if ($value !== null && is_numeric($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    private function pricesAreEqual(?string $priceA, ?string $priceB): bool
    {
        if ($priceA === null || $priceB === null) {
            return $priceA === $priceB;
        }

        return $this->calculator->cmp($priceA, $priceB, 4) === 0;
    }
}
