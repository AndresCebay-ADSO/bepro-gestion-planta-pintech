<?php

declare(strict_types=1);

namespace App\Services;

use App\Concerns\DeterminesPriceRefresh;
use App\Models\ProductVariant;

class VariantPricingService
{
    use DeterminesPriceRefresh;

    /**
     * Calcula y actualiza el costo y precio de una variante basándose en el costo del producto a granel
     * y el costo del envase.
     *
     * @param  ProductVariant  $variant  La variante a actualizar
     * @param  float  $bulkCost  Costo del producto a granel (base)
     * @param  float|null  $profitMargin  Margen de ganancia del producto (porcentaje)
     * @param  float  $priceThreshold  Umbral de variación de costo para actualizar el precio
     * @param  float  $packageUnitCost  Costo unitario del material de envase
     * @param  bool  $autoUpdatePrice  Si debe actualizar el precio automáticamente si supera el umbral
     * @param  bool  $forceRefresh  Si se debe forzar la actualización del precio ignorando el umbral
     */
    public function updateVariantCostAndPrice(
        ProductVariant $variant,
        float $bulkCost,
        ?float $profitMargin,
        float $priceThreshold,
        float $packageUnitCost = 0.0,
        bool $autoUpdatePrice = true,
        bool $forceRefresh = false
    ): void {
        $presentationValue = (float) ($variant->presentation_value ?? 1);

        // El nuevo costo de la variante es el costo a granel por la presentación + el costo del envase
        $newVariantCost = ($bulkCost * $presentationValue) + $packageUnitCost;

        $variantUpdates = ['current_cost' => $newVariantCost];

        if ($autoUpdatePrice && $profitMargin !== null) {
            $previousVariantCost = $variant->current_cost !== null ? (float) $variant->current_cost : null;
            $currentVariantPrice = $variant->current_price !== null ? (float) $variant->current_price : null;

            $shouldUpdateVariantPrice = $forceRefresh || $this->shouldUpdatePriceFromCostChange(
                currentPrice: $currentVariantPrice,
                previousCost: $previousVariantCost,
                newCost: $newVariantCost,
                threshold: $priceThreshold
            );

            if ($shouldUpdateVariantPrice) {
                $variantUpdates['current_price'] = $newVariantCost * (1 + ($profitMargin / 100));
            }
        }

        $variant->update($variantUpdates);
    }
}
