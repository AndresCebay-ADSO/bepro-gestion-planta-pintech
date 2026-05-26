<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductionCost;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use Illuminate\Support\Facades\DB;

class ProductionCostRecalculationService
{
    public function __construct(
        private readonly VariantPricingService $variantPricingService,
        private readonly DecimalCalculator $calculator
    ) {}

    public function recalculateForProduct(int $productId, bool $forcePriceRefresh = false): ?ProductionCost
    {
        $activeFormula = Formula::query()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->with(['details.rawMaterial:id,current_price'])
            ->first();

        if ($activeFormula === null) {
            return null;
        }

        return DB::transaction(function () use ($activeFormula, $productId, $forcePriceRefresh): ProductionCost {
            $calculatedCost = '0';
            foreach ($activeFormula->details as $detail) {
                $qty = (string) $detail->quantity;
                $price = (string) ($detail->rawMaterial->current_price ?? 0);
                $itemCost = $this->calculator->mul($qty, $price, 4);
                $calculatedCost = $this->calculator->add($calculatedCost, $itemCost, 4);
            }

            $previousCost = ProductionCost::query()
                ->where('product_id', $productId)
                ->whereNull('production_order_id')
                ->latest('calculated_at')
                ->latest('id')
                ->first();

            $variationPercentage = null;
            if ($previousCost !== null && ! $this->calculator->isZero($previousCost->cost)) {
                $difference = $this->calculator->sub($calculatedCost, (string) $previousCost->cost, 10);
                $ratio = $this->calculator->div($difference, (string) $previousCost->cost, 10);
                $variationPercentage = $this->calculator->round($this->calculator->mul($ratio, '100', 10), 4);
            }

            $product = Product::query()
                ->select('id', 'current_price', 'profit_margin', 'price_threshold')
                ->find($productId);
            $autoUpdateVariantPrice = (bool) config('production.auto_update_variant_price', true);
            $productProfitMargin = $product?->profit_margin !== null ? (string) $product->profit_margin : null;
            $priceThreshold = (string) ($product?->price_threshold ?? '0');

            if ($product !== null) {
                $productUpdates = ['current_cost' => $calculatedCost];

                $shouldUpdatePrice = $forcePriceRefresh
                    || $product->current_price === null
                    || ($variationPercentage !== null && $this->calculator->cmp(
                        $this->calculator->abs($variationPercentage, 4),
                        $priceThreshold,
                        4
                    ) >= 0);

                if ($shouldUpdatePrice && $product->profit_margin !== null) {
                    $profitMargin = (string) $product->profit_margin;
                    $marginRatio = $this->calculator->div($profitMargin, '100', 4);
                    $marginFactor = $this->calculator->add('1', $marginRatio, 4);
                    $productUpdates['current_price'] = $this->calculator->mul($calculatedCost, $marginFactor, 4);
                }

                $product->update($productUpdates);
            }

            $variants = ProductVariant::query()
                ->where('product_id', $productId)
                ->get(['id', 'presentation_value', 'package_raw_material_id', 'current_cost', 'current_price']);

            $packageMaterialIds = $variants
                ->pluck('package_raw_material_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $packageUnitPrices = RawMaterial::query()
                ->whereIn('id', $packageMaterialIds)
                ->pluck('current_price', 'id')
                ->map(fn ($price) => (string) ($price ?? '0'));

            $variants->each(function (ProductVariant $variant) use (
                $calculatedCost,
                $autoUpdateVariantPrice,
                $productProfitMargin,
                $priceThreshold,
                $packageUnitPrices,
                $forcePriceRefresh
            ): void {
                $packageUnitCost = $variant->package_raw_material_id !== null
                    ? (string) ($packageUnitPrices->get((int) $variant->package_raw_material_id) ?? '0')
                    : '0';

                $this->variantPricingService->updateVariantCostAndPrice(
                    variant: $variant,
                    bulkCost: $calculatedCost,
                    profitMargin: $productProfitMargin,
                    priceThreshold: $priceThreshold,
                    packageUnitCost: $packageUnitCost,
                    autoUpdatePrice: $autoUpdateVariantPrice,
                    forceRefresh: $forcePriceRefresh
                );
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
