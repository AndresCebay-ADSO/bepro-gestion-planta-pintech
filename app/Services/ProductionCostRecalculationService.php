<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductionCost;
use Illuminate\Support\Facades\DB;

class ProductionCostRecalculationService
{
    public function recalculateForProduct(int $productId): ?ProductionCost
    {
        $activeFormula = Formula::query()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->with(['details.rawMaterial:id,current_price'])
            ->first();

        if ($activeFormula === null) {
            return null;
        }

        return DB::transaction(function () use ($activeFormula, $productId): ProductionCost {
            $calculatedCost = (float) $activeFormula->details
                ->sum(fn ($detail) => (float) $detail->quantity * (float) $detail->rawMaterial->current_price);

            $previousCost = ProductionCost::query()
                ->where('product_id', $productId)
                ->whereNull('production_order_id')
                ->latest('calculated_at')
                ->latest('id')
                ->first();

            $variationPercentage = null;
            if ($previousCost !== null && (float) $previousCost->cost > 0) {
                $variationPercentage = (($calculatedCost - (float) $previousCost->cost) / (float) $previousCost->cost) * 100;
            }

            Product::query()
                ->where('id', $productId)
                ->update(['current_cost' => $calculatedCost]);

            return ProductionCost::create([
                'product_id' => $productId,
                'formula_id' => (int) $activeFormula->id,
                'production_order_id' => null,
                'cost' => $calculatedCost,
                'unit_cost' => $calculatedCost,
                'variation_percentage' => $variationPercentage,
                'calculated_at' => now(),
            ]);
        });
    }

    public function recalculateForRawMaterial(int $rawMaterialId): int
    {
        $productIds = Formula::query()
            ->where('is_active', true)
            ->whereHas('details', fn ($query) => $query->where('raw_material_id', $rawMaterialId))
            ->pluck('product_id')
            ->unique()
            ->values()
            ->all();

        $recalculated = 0;
        foreach ($productIds as $productId) {
            if ($this->recalculateForProduct((int) $productId) !== null) {
                $recalculated++;
            }
        }

        return $recalculated;
    }
}
