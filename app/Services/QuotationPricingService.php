<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProductVariant;

class QuotationPricingService
{
    public function __construct(
        private readonly DecimalCalculator $calculator,
        private readonly VariantSalesPriceService $salesPriceService
    ) {}

    public function resolveListUnitPrice(ProductVariant $variant): ?string
    {
        return $this->salesPriceService->resolveForVariant($variant);
    }

    /**
     * @return array{list_unit_price: string, price_adjustment_pct: string, unit_price: string}
     */
    public function resolveItemPricing(
        string $listUnitPrice,
        ?string $adjustmentPct = null,
        ?string $manualUnitPrice = null
    ): array {
        if ($manualUnitPrice !== null && $manualUnitPrice !== '') {
            $unitPrice = $this->calculator->round($manualUnitPrice, 4);
            $adjustmentPct = $this->resolveAdjustmentFromPrices($listUnitPrice, $unitPrice);

            return [
                'list_unit_price' => $listUnitPrice,
                'price_adjustment_pct' => $adjustmentPct,
                'unit_price' => $unitPrice,
            ];
        }

        $adjustmentPct = $adjustmentPct !== null && $adjustmentPct !== ''
            ? $this->calculator->round($adjustmentPct, 4)
            : '0';

        $factor = $this->calculator->add(
            '1',
            $this->calculator->div($adjustmentPct, '100', 4),
            4
        );

        $unitPrice = $this->calculator->mul($listUnitPrice, $factor, 4);

        return [
            'list_unit_price' => $listUnitPrice,
            'price_adjustment_pct' => $adjustmentPct,
            'unit_price' => $unitPrice,
        ];
    }

    public function calculateLineSubtotal(string $quantity, string $unitPrice): string
    {
        return $this->calculator->mul($quantity, $unitPrice, 4);
    }

    /**
     * @param  array<int, array{subtotal: string}>  $items
     * @return array{subtotal: string, iva_amount: string, total: string}
     */
    public function calculateQuotationTotals(array $items, string $ivaPercentage): array
    {
        $subtotal = '0';

        foreach ($items as $item) {
            $subtotal = $this->calculator->add($subtotal, $item['subtotal'], 4);
        }

        $ivaAmount = $this->calculator->mul(
            $subtotal,
            $this->calculator->div($ivaPercentage, '100', 4),
            4
        );

        $total = $this->calculator->add($subtotal, $ivaAmount, 4);

        return [
            'subtotal' => $subtotal,
            'iva_amount' => $ivaAmount,
            'total' => $total,
        ];
    }

    private function resolveAdjustmentFromPrices(string $listUnitPrice, string $unitPrice): string
    {
        if ($this->calculator->isZero($listUnitPrice, 4)) {
            return '0';
        }

        $difference = $this->calculator->sub($unitPrice, $listUnitPrice, 4);

        return $this->calculator->mul(
            $this->calculator->div($difference, $listUnitPrice, 4),
            '100',
            4
        );
    }
}
