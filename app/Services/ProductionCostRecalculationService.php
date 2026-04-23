<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductionCost;
use App\Models\ProductVariant;
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

            $product = Product::query()
                ->select('id', 'current_price', 'profit_margin', 'price_threshold')
                ->find($productId);

            if ($product !== null) {
                $productUpdates = ['current_cost' => $calculatedCost];

                $priceThreshold = (float) ($product->price_threshold ?? 0);
                $shouldUpdatePrice = $product->current_price === null
                    || ($variationPercentage !== null && abs($variationPercentage) >= $priceThreshold);

                if ($shouldUpdatePrice && $product->profit_margin !== null) {
                    $profitMargin = (float) $product->profit_margin;
                    $productUpdates['current_price'] = $calculatedCost * (1 + ($profitMargin / 100));
                }

                $product->update($productUpdates);
            }

            ProductVariant::query()
                ->where('product_id', $productId)
                ->get(['id', 'presentation_value'])
                ->each(function (ProductVariant $variant) use ($calculatedCost): void {
                    $presentationValue = (float) ($variant->presentation_value ?? 1);
                    $variant->update([
                        'current_cost' => $calculatedCost * $presentationValue,
                    ]);
                });

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
