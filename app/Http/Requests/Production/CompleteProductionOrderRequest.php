<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class CompleteProductionOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'actual_yield_quantity' => 'nullable|numeric|min:0',
            'viscosity_ku' => 'nullable|numeric|min:0',
            'grinding_hg' => 'nullable|numeric|min:0',
            'agitation_start_time' => 'nullable|date',
            'agitation_end_time' => 'nullable|date',
            'packaging_start_time' => 'nullable|date',
            'packaging_end_time' => 'nullable|date',
            'responsible_name' => 'nullable|string|max:255',
            'spillage_quantity' => 'nullable|numeric|min:0',
            'ingredients' => 'required|array',
            'ingredients.*.id' => 'required|exists:production_order_details,id',
            'ingredients.*.actual_quantity' => 'required|numeric|min:0',
            'packaging' => 'required|array',
            'packaging.*.id' => 'required|exists:production_order_packaging_plan,id',
            'packaging.*.actual_units' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ];
    }
}
