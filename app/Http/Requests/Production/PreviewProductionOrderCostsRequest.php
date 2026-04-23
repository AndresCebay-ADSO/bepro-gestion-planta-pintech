<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewProductionOrderCostsRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $order = $this->route('order');
        $orderId = is_object($order) ? $order->id : null;

        return [
            'ingredients' => 'required|array',
            'ingredients.*.id' => [
                'required',
                Rule::exists('production_order_details', 'id')
                    ->when($orderId !== null, fn ($query) => $query->where('production_order_id', $orderId)),
            ],
            'ingredients.*.actual_quantity' => 'required|numeric|min:0',
            'packaging' => 'array',
            'packaging.*.id' => [
                'required',
                Rule::exists('production_order_packaging_plan', 'id')
                    ->where('production_order_id', $orderId),
            ],
            'packaging.*.actual_units' => 'required|numeric|min:0',
        ];
    }
}
