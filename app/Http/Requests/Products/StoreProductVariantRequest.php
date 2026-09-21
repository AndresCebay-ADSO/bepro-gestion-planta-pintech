<?php

declare(strict_types=1);

namespace App\Http\Requests\Products;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product
            && ($this->user()?->can('manageVariants', $product) ?? false);
    }

    public function rules(): array
    {
        return [
            'code' => ['bail', 'required', 'string', 'max:80', Rule::unique('product_variants', 'code')],
            'name' => ['bail', 'required', 'string', 'max:100'],
            'unit_of_measure_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('unit_of_measures', 'id'),
            ],
            'presentation_value' => ['nullable', 'numeric', 'gt:0', 'decimal:0,4'],
            'presentation_label' => ['nullable', 'string', 'max:50'],
            'package_raw_material_id' => ['nullable', 'integer', Rule::exists('raw_materials', 'id')->where('is_active', true)],
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
