<?php

declare(strict_types=1);

namespace App\Http\Requests\RawMaterials;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRawMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'bail',
                'required',
                'string',
                'max:50',
                Rule::unique('raw_materials', 'code'),
            ],
            'category_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('raw_material_categories', 'id')->whereNull('deleted_at'),
            ],
            'unit_of_measure_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('unit_of_measures', 'id')->whereNull('deleted_at'),
            ],
            'minimum_stock' => ['bail', 'required', 'numeric', 'min:0', 'decimal:0,4'],
            'alert_days_before_expiry' => ['bail', 'required', 'integer', 'min:0'],
            'price_variation_threshold' => ['bail', 'nullable', 'numeric', 'min:0.01', 'max:100', 'decimal:0,2'],
            'tracks_inventory' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'category_id' => $this->input('category_id'),
            'minimum_stock' => $this->input('minimum_stock', 0),
            'alert_days_before_expiry' => $this->input('alert_days_before_expiry', 30),
            'price_variation_threshold' => $this->nullablePriceVariationThreshold(),
            'tracks_inventory' => $this->boolean('tracks_inventory', true),
            'is_active' => $this->boolean('is_active', true),
        ]);
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
