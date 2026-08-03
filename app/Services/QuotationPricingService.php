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
     *
     * Business Rule — Margin-based adjustment:
     * The price_adjustment_pct modifies the profit margin, not a simple
     * discount/surcharge. Positive values increase the margin (price goes up),
     * negative values decrease it (price goes down).
     *
     * Formula:  unit_price = list_unit_price / (1 - adjustmentPct / 100)
     *
     * Examples:
     *   adjustment = +15%  →  divisor = 0.85   →  price = list / 0.85
     *   adjustment = -15%  →  divisor = 1.15   →  price = list / 1.15
     *   adjustment =   0%  →  divisor = 1.00   →  price = list
     *
     * This is intentionally asymmetric: a +15% and -15% adjustment do NOT
     * cancel each other out, because they operate on the margin space.
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

        $adjustmentDecimal = $this->calculator->div($adjustmentPct, '100', 4);
        $divisor = $this->calculator->sub('1', $adjustmentDecimal, 4);

        if ($this->calculator->cmp($divisor, '0', 4) <= 0) {
            $unitPrice = '0';
        } else {
            $unitPrice = $this->calculator->div($listUnitPrice, $divisor, 4);
        }

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

        if ($this->calculator->isZero($unitPrice, 4)) {
            return '0';
        }

        $ratio = $this->calculator->div($listUnitPrice, $unitPrice, 4);

        return $this->calculator->mul(
            $this->calculator->sub('1', $ratio, 4),
            '100',
            4
        );
    }
}
