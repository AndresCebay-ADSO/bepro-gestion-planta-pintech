<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'produccion']) ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['bail', 'required', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-_]+$/', Rule::unique('products', 'code')],
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
                Rule::exists('units_of_measure', 'id')->whereNull('deleted_at'),
            ],
            'current_cost' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'profit_margin' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'current_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'price_threshold' => ['bail', 'required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
