<?php

declare(strict_types=1);

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
            'code' => ['bail', 'required', 'string', 'max:80', Rule::unique('product_variants', 'code')->ignore($variantId)],
            'name' => ['bail', 'required', 'string', 'max:100'],
            'unit_of_measure_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('unit_of_measures', 'id')->whereNull('deleted_at'),
            ],
            'presentation_value' => ['nullable', 'numeric', 'gt:0', 'decimal:0,4'],
            'presentation_label' => ['nullable', 'string', 'max:50'],
            'package_raw_material_id' => [
                'nullable',
                'integer',
                Rule::exists('raw_materials', 'id')->when(
                    $this->package_raw_material_id && (int) $this->package_raw_material_id !== (int) ($this->route('variant')?->package_raw_material_id),
                    fn ($rule) => $rule->where('is_active', true)
                ),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['presentation_value', 'package_raw_material_id'] as $key) {
            if ($this->has($key) && ($this->input($key) === '' || $this->input($key) === null)) {
                $this->merge([$key => null]);
            }
        }
    }
}
