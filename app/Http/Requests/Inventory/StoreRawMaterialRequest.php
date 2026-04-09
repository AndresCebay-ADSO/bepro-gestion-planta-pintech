<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRawMaterialRequest extends FormRequest
{
    private const MAX_PRICE = 99999999999999.9999;

    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'produccion']) ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['bail', 'required', 'string', 'max:50', Rule::unique('raw_materials', 'code')],
            'unit_of_measure_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('units_of_measure', 'id')->whereNull('deleted_at'),
            ],
            'current_price' => ['bail', 'required', 'numeric', 'min:0', 'max:'.self::MAX_PRICE, 'decimal:0,4'],
            'previous_price' => ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_PRICE, 'decimal:0,4'],
            'minimum_stock' => ['bail', 'required', 'numeric', 'min:0', 'decimal:0,4'],
            'alert_days_before_expiry' => ['bail', 'required', 'integer', 'min:0', 'max:3650'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
