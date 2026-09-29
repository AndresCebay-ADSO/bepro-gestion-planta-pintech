<?php

declare(strict_types=1);

namespace App\Http\Requests\RawMaterials;

use App\Enums\RawMaterialType;
use App\Http\Requests\Concerns\CatalogSelectionRules;
use App\Models\FormulaDetail;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRawMaterialRequest extends FormRequest
{
    use CatalogSelectionRules;

    public function authorize(): bool
    {
        $rawMaterial = $this->route('raw_material');

        return $rawMaterial instanceof RawMaterial
            && ($this->user()?->can('update', $rawMaterial) ?? false);
    }

    public function rules(): array
    {
        $rawMaterial = $this->route('raw_material');
        $rawMaterialId = is_object($rawMaterial) ? $rawMaterial->id : $rawMaterial;

        return [
            'code' => [
                'bail',
                'required',
                'string',
                'max:50',
                Rule::unique('raw_materials', 'code')->ignore($rawMaterialId),
            ],
            'category_id' => $this->activeOrCurrentRules('raw_material_categories', 'category_id', $this->route('raw_material')?->category_id),
            'unit_of_measure_id' => $this->activeOrCurrentRules('unit_of_measures', 'unit_of_measure_id', $this->route('raw_material')?->unit_of_measure_id),
            'minimum_stock' => ['bail', 'required', 'numeric', 'min:0', 'decimal:0,4'],
            'alert_days_before_expiry' => ['bail', 'required', 'integer', 'min:0'],
            'price_variation_threshold' => ['bail', 'nullable', 'numeric', 'min:0.01', 'max:100', 'decimal:0,2'],
            'tracks_inventory' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Cambiar de categoría puede cambiar el tipo de insumo. Si la materia prima ya se usa en algo que depende de su tipo
     * (el envase de una presentación, una línea de fórmula), pasarla a otro tipo dejaría ese uso incoherente: la
     * presentación o la fórmula ya no podrían editarse. Moverla a otra categoría del mismo tipo siempre se puede.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $material = $this->route('raw_material');

                if (! $material instanceof RawMaterial || $validator->errors()->has('category_id')) {
                    return;
                }

                $newCategory = RawMaterialCategory::query()->find((int) $this->input('category_id'));

                if ($newCategory === null || $newCategory->id === $material->category_id) {
                    return;
                }

                // Sin categoría cuenta como Químico, igual que en RawMaterial::scopeUsableInFormulas().
                $currentType = $material->category?->type ?? RawMaterialType::Chemical;

                if ($newCategory->type !== $currentType) {
                    $message = $this->typeDependentUsage($material, $currentType, $newCategory->type);

                    if ($message !== null) {
                        $validator->errors()->add('category_id', $message);
                    }
                }
            },
        ];
    }

    private function typeDependentUsage(RawMaterial $material, RawMaterialType $current, RawMaterialType $new): ?string
    {
        $type = mb_strtolower($new->label());

        // Las etiquetas (3.7) y el empaque secundario (3.8) se sumarán aquí cuando tengan usos que dependan del tipo.
        return match ($current) {
            RawMaterialType::Container => ($count = ProductVariant::query()->where('package_raw_material_id', $material->id)->count()) > 0
                ? trans_choice(
                    'No se puede pasar a una categoría de tipo :type: esta materia prima es el envase de :count presentación.|No se puede pasar a una categoría de tipo :type: esta materia prima es el envase de :count presentaciones.',
                    $count,
                    ['type' => $type, 'count' => $count],
                )
                : null,
            RawMaterialType::Chemical => ($count = FormulaDetail::query()->where('raw_material_id', $material->id)->count()) > 0
                ? trans_choice(
                    'No se puede pasar a una categoría de tipo :type: esta materia prima está en :count línea de fórmula.|No se puede pasar a una categoría de tipo :type: esta materia prima está en :count líneas de fórmula.',
                    $count,
                    ['type' => $type, 'count' => $count],
                )
                : null,
            default => null,
        };
    }

    protected function prepareForValidation(): void
    {
        $prepared = [
            'category_id' => $this->input('category_id'),
            'minimum_stock' => $this->input('minimum_stock', 0),
            'alert_days_before_expiry' => $this->input('alert_days_before_expiry', 30),
            'price_variation_threshold' => $this->nullablePriceVariationThreshold(),
            'is_active' => $this->boolean('is_active', true),
        ];

        if ($this->has('tracks_inventory')) {
            $prepared['tracks_inventory'] = $this->boolean('tracks_inventory');
        }

        $this->merge($prepared);
    }

    private function nullablePriceVariationThreshold(): ?string
    {
        $value = $this->input('price_variation_threshold');

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
