<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;

class VariantSalesPriceService
{
    public function __construct(
        private readonly DecimalCalculator $calculator
    ) {}

    public function resolveForProduct(Product $product): ?string
    {
        if ($product->current_price === null) {
            return null;
        }

        return $this->applySalesMargin(
            (string) $product->current_price,
            $product->sales_margin
        );
    }

    public function resolveForVariant(ProductVariant $variant): ?string
    {
        if ($variant->current_price === null) {
            return null;
        }

        $variant->loadMissing('product:id,sales_margin');

        return $this->applySalesMargin(
            (string) $variant->current_price,
            $variant->product?->sales_margin
        );
    }

    public function applySalesMargin(string $basePrice, float|string|null $salesMargin): string
    {
        $margin = $salesMargin !== null ? (string) $salesMargin : '0';
        $marginDecimal = $this->calculator->div($margin, '100', 4);
        $multiplier = $this->calculator->add('1', $marginDecimal, 4);

        return $this->calculator->mul($basePrice, $multiplier, 4);
    }
}
