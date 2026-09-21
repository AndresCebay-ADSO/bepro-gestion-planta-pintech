<?php

declare(strict_types=1);

namespace App\Http\Requests\Pricing;

use App\Models\Product;
use App\Services\DecimalCalculator;
use App\Services\VariantSalesPriceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Margen de venta de un producto: directo (`sales_margin`) o derivado de un precio de venta (`sales_price`). Todo el
 * cálculo es decimal (bcmath), nunca con floats.
 */
class UpdateCostRequest extends FormRequest
{
    private ?string $resolvedMargin = null;

    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product
            && ($this->user()?->can('updateCost', $product) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sales_margin' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'sales_price' => ['nullable', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $salesPrice = $this->input('sales_price');

                if ($salesPrice === null || $salesPrice === '') {
                    $margin = $this->input('sales_margin');
                    $this->resolvedMargin = $margin === null || $margin === '' ? null : (string) $margin;

                    return;
                }

                /** @var Product $product */
                $product = $this->route('product');
                $calculator = app(DecimalCalculator::class);
                $basePrice = (string) ($product->current_price ?? '0');

                if (! $calculator->isPositive($basePrice)) {
                    $validator->errors()->add('sales_price', 'No se puede calcular el margen porque el producto no tiene precio interno.');

                    return;
                }

                $margin = app(VariantSalesPriceService::class)->resolveMarginFromSalesPrice($basePrice, (string) $salesPrice);

                if ($calculator->isNegative($margin) || $calculator->cmp($margin, '100') >= 0) {
                    $validator->errors()->add('sales_price', 'El precio ingresado genera un margen inválido (debe estar entre 0% y 99.99%).');

                    return;
                }

                $this->resolvedMargin = $margin;
            },
        ];
    }

    /**
     * Margen resultante (decimal en texto) o `null` para borrarlo. Solo tras validar.
     */
    public function salesMargin(): ?string
    {
        return $this->resolvedMargin;
    }
}
