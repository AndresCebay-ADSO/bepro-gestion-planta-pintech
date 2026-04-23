<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use App\Models\ProductionOrderPackagingPlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $order = $this->route('order');
        $orderId = is_object($order) ? $order->id : null;

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
            'notes' => 'nullable|string',
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
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $actualYield = $this->input('actual_yield_quantity');
                if ($actualYield === null) {
                    return;
                }

                $packaging = $this->input('packaging');
                if (! is_array($packaging) || $packaging === []) {
                    return;
                }

                $packagingIds = collect($packaging)
                    ->pluck('id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();

                if ($packagingIds === []) {
                    return;
                }

                $plans = ProductionOrderPackagingPlan::query()
                    ->whereIn('id', $packagingIds)
                    ->with('productVariant:id,presentation_value')
                    ->get()
                    ->keyBy('id');

                $expectedYield = 0.0;
                foreach ($packaging as $packagingData) {
                    $planId = (int) ($packagingData['id'] ?? 0);
                    $plan = $plans->get($planId);
                    if ($plan === null) {
                        continue;
                    }

                    $actualUnits = (float) ($packagingData['actual_units'] ?? 0);
                    if ($actualUnits <= 0) {
                        continue;
                    }

                    $presentationValue = (float) ($plan->productVariant?->presentation_value ?? 1);
                    $expectedYield += ($actualUnits * $presentationValue);
                }

                $difference = abs((float) $actualYield - $expectedYield);
                $yieldTolerance = (float) config('production.yield_tolerance', 0.01);

                if ($difference <= $yieldTolerance) {
                    return;
                }

                $validator->errors()->add(
                    'actual_yield_quantity',
                    "El rendimiento real debe coincidir con el envasado equivalente. Registrado: {$actualYield}, esperado: {$expectedYield} (tolerancia: {$yieldTolerance})."
                );
            },
        ];
    }
}
