<?php

declare(strict_types=1);

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'produccion']) ?? false;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return array_merge(
            [
                'code' => ['bail', 'nullable', 'string', 'max:50', Rule::unique('products', 'code')],
            ],
            $this->baseRules()
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function baseRules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'min:3', 'max:150'],
            'brand' => ['bail', 'required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:10000'],
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
            'cif_percentage' => ['bail', 'required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'current_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'price_threshold' => ['bail', 'required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'quality_viscosity_lower' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'quality_viscosity_upper' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'quality_fineness_lower' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'quality_fineness_upper' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'quality_solids_lower' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'quality_solids_upper' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->mergeEmptyQualityAndDescription();

        if (! $this->filled('brand') || $this->string('brand')->trim()->isEmpty()) {
            $this->merge(['brand' => 'BEPRO']);
        }
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->assertQualityRange($validator, 'quality_viscosity_lower', 'quality_viscosity_upper', 'Viscosidad');
                $this->assertQualityRange($validator, 'quality_fineness_lower', 'quality_fineness_upper', 'Molienda');
                $this->assertQualityRange($validator, 'quality_solids_lower', 'quality_solids_upper', 'Sólidos');
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'código',
            'name' => 'nombre',
            'brand' => 'marca',
            'description' => 'descripción',
            'category_id' => 'categoría',
            'unit_of_measure_id' => 'unidad de medida',
            'current_cost' => 'costo actual',
            'cif_percentage' => 'porcentaje CIF',
            'current_price' => 'precio actual',
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

    protected function mergeEmptyQualityAndDescription(): void
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
