<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use App\Enums\ProductionOrderStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLineAdjustmentRequest extends FormRequest
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
        return [
            'raw_material_id' => [
                'required',
                Rule::exists('raw_materials', 'id')->where('is_active', true),
            ],
            'quantity' => 'required|numeric|min:0.0001',
            'reason' => 'required|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $order = $this->route('order');
                if (! is_object($order)) {
                    return;
                }

                $blockedStatuses = [
                    ProductionOrderStatus::Completed,
                    ProductionOrderStatus::Cancelled,
                ];

                if (in_array($order->status, $blockedStatuses, true)) {
                    $validator->errors()->add(
                        'production_order',
                        'No se pueden agregar ajustes a una orden completada o cancelada.'
                    );
                }
            },
        ];
    }
}
