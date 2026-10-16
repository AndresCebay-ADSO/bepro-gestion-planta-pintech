<?php

declare(strict_types=1);

namespace App\Http\Requests\Products;

use App\Models\Product;

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
        $rules = $this->baseRules();

        // Quien no ve costos no recibe CIF ni umbral y no los envía: si faltan, se conservan los guardados.
        foreach (['cif_percentage', 'price_threshold'] as $field) {
            $rules[$field] = ['sometimes', ...$rules[$field]];
        }

        return $rules;
    }
}
