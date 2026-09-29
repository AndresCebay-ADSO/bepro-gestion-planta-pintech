<?php

declare(strict_types=1);

namespace App\Http\Requests\Products;

use App\Enums\RawMaterialType;
use App\Http\Requests\Concerns\CatalogSelectionRules;
use App\Models\Product;
use App\Rules\RawMaterialOfType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductVariantRequest extends FormRequest
{
    use CatalogSelectionRules;

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
            'unit_of_measure_id' => $this->activeOrCurrentRules('unit_of_measures', 'unit_of_measure_id'),
            'presentation_value' => ['nullable', 'numeric', 'gt:0', 'decimal:0,4'],
            'presentation_label' => ['nullable', 'string', 'max:50'],
            // B40: el envase debe ser una materia prima de tipo Envase, no cualquiera activa.
            'package_raw_material_id' => ['nullable', 'integer', Rule::exists('raw_materials', 'id')->where('is_active', true), new RawMaterialOfType(RawMaterialType::Container)],
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
