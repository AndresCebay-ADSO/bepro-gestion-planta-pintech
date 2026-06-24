<?php

declare(strict_types=1);

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'produccion']) ?? false;
    }

    public function rules(): array
    {
        return [
            'sku' => ['bail', 'required', 'string', 'max:80', Rule::unique('product_variants', 'sku')],
            'unit_of_measure_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('unit_of_measures', 'id')->whereNull('deleted_at'),
            ],
            'presentation_value' => ['nullable', 'numeric', 'gt:0', 'decimal:0,4'],
            'presentation_label' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:100'],
            'finish' => ['nullable', 'string', 'max:50'],
            'base_type' => ['nullable', 'string', 'max:50'],
            'component_system' => ['bail', 'required', Rule::in(['1K', '2K', 'KIT'])],
            'current_cost' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'current_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'package_raw_material_id' => ['nullable', 'integer', Rule::exists('raw_materials', 'id')->where('is_active', true)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
