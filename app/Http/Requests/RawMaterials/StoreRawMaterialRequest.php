<?php

namespace App\Http\Requests\RawMaterials;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRawMaterialRequest extends FormRequest
{
    private const MAX_PRICE = '99999999999999.9999';

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
                Rule::unique('raw_materials', 'code')->whereNull('deleted_at'),
            ],
            'unit_of_measure_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('units_of_measure', 'id')->whereNull('deleted_at'),
            ],
            'current_price' => ['bail', 'required', 'numeric', 'min:0', 'max:'.self::MAX_PRICE, 'decimal:0,4'],
            'previous_price' => ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_PRICE, 'decimal:0,4'],
            'minimum_stock' => ['bail', 'required', 'numeric', 'min:0', 'decimal:0,4'],
            'alert_days_before_expiry' => ['bail', 'required', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'minimum_stock' => $this->input('minimum_stock', 0),
            'alert_days_before_expiry' => $this->input('alert_days_before_expiry', 30),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
