<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'produccion']) ?? false;
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $productId = is_object($product) ? $product->id : $product;

        return [
            'code' => [
                'bail',
                'nullable',
                'string',
                'max:50',
                Rule::unique('products', 'code')->ignore($productId),
            ],
            'name' => ['bail', 'required', 'string', 'min:3', 'max:150'],
            'category_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('product_categories', 'id')->whereNull('deleted_at'),
            ],
            'unit_of_measure_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('unit_of_measures', 'id')->whereNull('deleted_at'),
            ],
            'current_cost' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'profit_margin' => ['bail', 'required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'current_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'price_threshold' => ['bail', 'required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
