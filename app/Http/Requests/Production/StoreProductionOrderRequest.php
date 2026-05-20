<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use App\Enums\WarehouseType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductionOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Gates handle this in controller or middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'formula_id' => [
                'required',
                Rule::exists('formulas', 'id')
                    ->where('product_id', $this->input('product_id'))
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'warehouse_id' => [
                'required',
                Rule::exists('warehouses', 'id')
                    ->where('type', WarehouseType::Factory->value)
                    ->where('is_active', true),
            ],
            'quantity' => 'required|numeric|min:0.01',
            'planned_date' => 'required|date',
            'notes' => 'nullable|string',
            'packaging' => 'nullable|array',
            'packaging.*.product_variant_id' => 'required_with:packaging|exists:product_variants,id',
            'packaging.*.planned_units' => 'required_with:packaging|numeric|min:0.01',
        ];
    }
}
