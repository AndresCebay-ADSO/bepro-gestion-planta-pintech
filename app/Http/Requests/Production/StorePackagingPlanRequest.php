<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use App\Models\ProductionOrderPackagingPlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePackagingPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('updateOperationalData', $this->route('production_order')) ?? false;
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
        $orderId = is_object($order) ? $order->id : null;

        return [
            'product_variant_id' => [
                'required',
                Rule::exists('product_variants', 'id')
                    ->where('is_active', true)
                    ->when($productId !== null, fn ($query) => $query->where('product_id', $productId)),
                // Una presentación va una sola vez por orden: cada una deja un lote de PT (B55).
                Rule::unique('production_order_packaging_plan', 'product_variant_id')
                    ->where('production_order_id', $orderId),
            ],
            'planned_units' => 'required|numeric|min:1',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_variant_id.unique' => __(ProductionOrderPackagingPlan::DUPLICATE_PRESENTATION_MESSAGE),
        ];
    }
}
