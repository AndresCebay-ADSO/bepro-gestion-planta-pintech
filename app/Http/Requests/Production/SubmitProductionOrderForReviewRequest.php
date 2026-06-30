<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitProductionOrderForReviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('submitForReview', $this->route('production_order')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $order = $this->route('production_order');
        $orderId = is_object($order) ? $order->id : null;

        return [
            'actual_yield_quantity' => 'nullable|numeric|min:0',
            'viscosity_ku' => 'nullable|numeric|min:0',
            'grinding_hg' => 'nullable|numeric|min:0',
            'quality_solids' => 'nullable|numeric|min:0|max:100',
            'agitation_start_time' => 'nullable|date',
            'agitation_end_time' => 'nullable|date',
            'packaging_start_time' => 'nullable|date',
            'packaging_end_time' => 'nullable|date',
            'responsible_name' => 'nullable|string|max:255',
            'spillage_quantity' => 'nullable|numeric|min:0',
            'ingredients' => 'required|array',
            'ingredients.*.id' => [
                'required',
                'distinct:strict',
                Rule::exists('production_order_details', 'id')
                    ->when($orderId !== null, fn ($query) => $query->where('production_order_id', $orderId)),
            ],
            'ingredients.*.actual_quantity' => 'required|numeric|min:0',
            'packaging' => 'array',
            'packaging.*.id' => [
                'required',
                'distinct:strict',
                Rule::exists('production_order_packaging_plan', 'id')
                    ->where('production_order_id', $orderId),
            ],
            'packaging.*.actual_units' => 'required|numeric|min:0',
            'density_kg_per_gallon' => ['nullable', 'numeric', 'min:0.0001'],
            'remnant_quantity_gallons' => ['nullable', 'numeric', 'min:0'],
            'remnant_notes' => ['nullable', 'string', 'max:1000'],
            'notes' => 'nullable|string',
        ];
    }
}
