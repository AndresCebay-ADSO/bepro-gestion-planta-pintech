<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'formula_id' => 'required|exists:formulas,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0.01',
            'planned_date' => 'required|date',
            'notes' => 'nullable|string',
            'packaging' => 'nullable|array',
            'packaging.*.product_variant_id' => 'required_with:packaging|exists:product_variants,id',
            'packaging.*.planned_units' => 'required_with:packaging|numeric|min:0.01',
        ];
    }
}
