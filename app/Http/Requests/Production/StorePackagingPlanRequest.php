<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use App\Enums\ProductionOrderStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePackagingPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('production_order')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $order = $this->route('production_order');
        $productId = is_object($order) ? $order->product_id : null;

        return [
            'product_variant_id' => [
                'required',
                Rule::exists('product_variants', 'id')
                    ->where('is_active', true)
                    ->when($productId !== null, fn ($query) => $query->where('product_id', $productId)),
            ],
            'planned_units' => 'required|numeric|min:1',
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
                $order = $this->route('production_order');
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
                        'No se pueden agregar planes de envasado a una orden completada o cancelada.'
                    );
                }
            },
        ];
    }
}
