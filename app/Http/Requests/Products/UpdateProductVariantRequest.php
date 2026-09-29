<?php

declare(strict_types=1);

namespace App\Http\Requests\Products;

use App\Enums\RawMaterialType;
use App\Http\Requests\Concerns\CatalogSelectionRules;
use App\Models\Product;
use App\Rules\RawMaterialOfType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductVariantRequest extends FormRequest
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
        $variant = $this->route('variant');
        $variantId = is_object($variant) ? $variant->id : $variant;

        return [
            'code' => ['bail', 'required', 'string', 'max:80', Rule::unique('product_variants', 'code')->ignore($variantId)],
            'name' => ['bail', 'required', 'string', 'max:100'],
            'unit_of_measure_id' => $this->activeOrCurrentRules('unit_of_measures', 'unit_of_measure_id', $this->route('variant')?->unit_of_measure_id),
            'presentation_value' => ['nullable', 'numeric', 'gt:0', 'decimal:0,4'],
            'presentation_label' => ['nullable', 'string', 'max:50'],
            'package_raw_material_id' => [
                'nullable',
                'integer',
                Rule::exists('raw_materials', 'id')->when(
                    $this->package_raw_material_id && (int) $this->package_raw_material_id !== (int) ($this->route('variant')?->package_raw_material_id),
                    fn ($rule) => $rule->where('is_active', true)
                ),
                // B40: el envase debe ser una materia prima de tipo Envase.
                new RawMaterialOfType(RawMaterialType::Container),
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
