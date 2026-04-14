<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'produccion']) ?? false;
    }

    public function rules(): array
    {
        $variant = $this->route('variant');
        $variantId = is_object($variant) ? $variant->id : $variant;

        return [
            'sku' => ['bail', 'required', 'string', 'max:80', Rule::unique('product_variants', 'sku')->ignore($variantId)],
            'unit_of_measure_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('units_of_measure', 'id')->whereNull('deleted_at'),
            ],
            'presentation_value' => ['nullable', 'numeric', 'gt:0', 'decimal:0,4'],
            'presentation_label' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:100'],
            'finish' => ['nullable', 'string', 'max:50'],
            'base_type' => ['nullable', 'string', 'max:50'],
            'component_system' => ['bail', 'required', Rule::in(['1K', '2K', 'KIT'])],
            'current_cost' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'current_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
