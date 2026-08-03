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
            $this->toDecimalString($product->current_price, 4),
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
            $this->toDecimalString($variant->current_price, 4),
            $variant->product?->sales_margin
        );
    }

    public function applySalesMargin(string|float $basePrice, float|string|null $salesMargin): ?string
    {
        $base = $this->toDecimalString($basePrice, 4);
        $margin = $salesMargin !== null ? (string) $salesMargin : '0';
        $marginDecimal = $this->calculator->div($margin, '100', 4);
        $divisor = $this->calculator->sub('1', $marginDecimal, 4);

        if ($this->calculator->cmp($divisor, '0', 4) <= 0) {
            return null;
        }

        return $this->calculator->div($base, $divisor, 4);
    }

    public function resolveMarginFromSalesPrice(string|float $basePrice, string|float $salesPrice): string
    {
        $base = $this->toDecimalString($basePrice, 6);
        $sale = $this->toDecimalString($salesPrice, 6);

        $ratio = $this->calculator->div($base, $sale, 6);
        $marginDecimal = $this->calculator->sub('1', $ratio, 6);

        return $this->calculator->mul($marginDecimal, '100', 2);
    }

    private function toDecimalString(string|float $value, int $scale = 4): string
    {
        return is_string($value) ? $value : number_format((float) $value, $scale, '.', '');
    }
}
