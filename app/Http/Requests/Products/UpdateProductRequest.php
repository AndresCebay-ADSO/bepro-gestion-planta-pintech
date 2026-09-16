<?php

declare(strict_types=1);

namespace App\Http\Requests\Products;

use App\Models\Product;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends StoreProductRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product
            && ($this->user()?->can('update', $product) ?? false);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        $product = $this->route('product');
        $productId = is_object($product) ? $product->id : $product;

        $rules = array_merge(
            [
                'code' => [
                    'bail',
                    'nullable',
                    'string',
                    'max:50',
                    Rule::unique('products', 'code')->ignore($productId),
                ],
            ],
            $this->baseRules()
        );

        // Quien no ve costos no recibe CIF ni umbral y no los envía: si faltan, se conservan los guardados.
        foreach (['cif_percentage', 'price_threshold'] as $field) {
            $rules[$field] = ['sometimes', ...$rules[$field]];
        }

        return $rules;
    }
}
