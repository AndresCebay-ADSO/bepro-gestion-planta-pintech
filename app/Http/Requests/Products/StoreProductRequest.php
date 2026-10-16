<?php

declare(strict_types=1);

namespace App\Http\Requests\Products;

use App\Enums\NumberType;
use App\Enums\QualityMeasurement;
use App\Http\Requests\Concerns\CatalogSelectionRules;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProductRequest extends FormRequest
{
    use CatalogSelectionRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) ?? false;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return $this->baseRules();
    }

    /**
     * @return array<string, mixed>
     */
    protected function baseRules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'min:3', 'max:150'],
            'description' => ['nullable', 'string', 'max:10000'],
            'category_id' => $this->activeOrCurrentRules('product_categories', 'category_id', $this->route('product')?->category_id),
            'unit_of_measure_id' => $this->activeOrCurrentRules('unit_of_measures', 'unit_of_measure_id', $this->route('product')?->unit_of_measure_id),
            // El CIF es un recargo sobre el costo (costo × (1 + CIF)): puede pasar de 100 %, hasta el tope de su columna.
            'cif_percentage' => ['bail', 'required', ...NumberType::Percentage->rules(), 'min:0'],
            'price_threshold' => ['bail', 'required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            // Un decimal y el tope de cada medición, los mismos que al medir en la OP (PRD-01).
            'quality_viscosity_lower' => QualityMeasurement::Viscosity->rules(),
            'quality_viscosity_upper' => QualityMeasurement::Viscosity->rules(),
            'quality_fineness_lower' => QualityMeasurement::Fineness->rules(),
            'quality_fineness_upper' => QualityMeasurement::Fineness->rules(),
            'quality_solids_lower' => QualityMeasurement::Solids->rules(),
            'quality_solids_upper' => QualityMeasurement::Solids->rules(),
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeEmptyInputs();
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->assertQualityRange($validator, 'quality_viscosity_lower', 'quality_viscosity_upper', QualityMeasurement::Viscosity->label());
                $this->assertQualityRange($validator, 'quality_fineness_lower', 'quality_fineness_upper', QualityMeasurement::Fineness->label());
                $this->assertQualityRange($validator, 'quality_solids_lower', 'quality_solids_upper', QualityMeasurement::Solids->label());
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'description' => 'descripción',
            'category_id' => 'categoría',
            'unit_of_measure_id' => 'unidad de medida',
            'current_cost' => 'costo actual',
            'cif_percentage' => 'porcentaje CIF',
            'current_price' => 'precio interno',
            'price_threshold' => 'umbral de precio',
            'quality_viscosity_lower' => 'viscosidad mínima',
            'quality_viscosity_upper' => 'viscosidad máxima',
            'quality_fineness_lower' => 'molienda mínima',
            'quality_fineness_upper' => 'molienda máxima',
            'quality_solids_lower' => 'sólidos mínimos',
            'quality_solids_upper' => 'sólidos máximos',
            'is_active' => 'activo',
        ];
    }

    protected function normalizeEmptyInputs(): void
    {
        $keys = [
            'description',
            'quality_viscosity_lower',
            'quality_viscosity_upper',
            'quality_fineness_lower',
            'quality_fineness_upper',
            'quality_solids_lower',
            'quality_solids_upper',
        ];

        foreach ($keys as $key) {
            if (! $this->has($key)) {
                continue;
            }

            $value = $this->input($key);

            if ($value === '' || $value === null) {
                $this->merge([$key => null]);
            }
        }
    }

    protected function assertQualityRange(Validator $validator, string $lowerKey, string $upperKey, string $label): void
    {
        if ($validator->errors()->has($lowerKey) || $validator->errors()->has($upperKey)) {
            return;
        }

        $lower = $this->input($lowerKey);
        $upper = $this->input($upperKey);

        if ($lower === null || $upper === null) {
            return;
        }

        if ((float) $lower > (float) $upper) {
            $validator->errors()->add(
                $upperKey,
                "{$label}: el valor superior debe ser mayor o igual al inferior."
            );
        }
    }
}
