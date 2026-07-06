<?php

declare(strict_types=1);

namespace App\Services;

use App\Concerns\DeterminesPriceRefresh;
use App\Models\ProductVariant;

class VariantPricingService
{
    use DeterminesPriceRefresh;

    public function __construct(
        private readonly DecimalCalculator $calculator
    ) {}

    /**
     * Calcula y actualiza el costo y precio de una variante basándose en el costo del producto a granel
     * y el costo del envase.
     *
     * @param  ProductVariant  $variant  La variante a actualizar
     * @param  string|float  $bulkCost  Costo del producto a granel (base)
     * @param  string|float|null  $cifPercentage  CIF del producto (porcentaje)
     * @param  string|float  $priceThreshold  Umbral de variación de costo para actualizar el precio
     * @param  string|float  $packageUnitCost  Costo unitario del material de envase
     * @param  bool  $autoUpdatePrice  Si debe actualizar el precio automáticamente si supera el umbral
     * @param  bool  $forceRefresh  Si se debe forzar la actualización del precio ignorando el umbral
     */
    public function updateVariantCostAndPrice(
        ProductVariant $variant,
        string|float $bulkCost,
        string|float|null $cifPercentage,
        string|float $priceThreshold,
        string|float $packageUnitCost = '0',
        bool $autoUpdatePrice = true,
        bool $forceRefresh = false
    ): void {
        $bulkCostStr = (string) $bulkCost;
        $presentationValue = (string) ($variant->presentation_value ?? 1);
        $packageUnitCostStr = (string) $packageUnitCost;

        // El nuevo costo de la variante es el costo a granel por la presentación + el costo del envase
        $variantCostProduct = $this->calculator->mul($bulkCostStr, $presentationValue, 4);
        $newVariantCost = $this->calculator->add($variantCostProduct, $packageUnitCostStr, 4);

        $variantUpdates = ['current_cost' => $newVariantCost];

        if ($autoUpdatePrice && $cifPercentage !== null) {
            $previousVariantCost = $variant->current_cost !== null ? (string) $variant->current_cost : null;
            $currentVariantPrice = $variant->current_price !== null ? (string) $variant->current_price : null;

            $shouldUpdateVariantPrice = $forceRefresh || $this->shouldUpdatePriceFromCostChange(
                currentPrice: $currentVariantPrice,
                previousCost: $previousVariantCost,
                newCost: $newVariantCost,
                threshold: (string) $priceThreshold
            );

            if ($shouldUpdateVariantPrice) {
                $cifPercentageStr = (string) $cifPercentage;
                $cifRatio = $this->calculator->div($cifPercentageStr, '100', 4);
                $cifFactor = $this->calculator->add('1', $cifRatio, 4);
                $newPrice = $this->calculator->mul($newVariantCost, $cifFactor, 4);
                $variantUpdates['current_price'] = $newPrice;
            }
        }

        $variant->update($variantUpdates);
    }
}
