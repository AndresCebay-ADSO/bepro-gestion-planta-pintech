<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use App\Models\ProductionOrder;
use Illuminate\Validation\Rule;

trait ProductionConsumptionRules
{
    protected function resolveProductionOrderId(): ?int
    {
        $order = $this->route('production_order');

        if ($order instanceof ProductionOrder) {
            return $order->id;
        }

        if (is_numeric($order)) {
            return (int) $order;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function consumptionRules(?int $orderId = null): array
    {
        $orderId ??= $this->resolveProductionOrderId();
        $scopedOrderId = $orderId ?? 0;

        return [
            'ingredients' => ['required', 'array'],
            'ingredients.*.id' => [
                'required',
                'distinct:strict',
                Rule::exists('production_order_details', 'id')
                    ->where('production_order_id', $scopedOrderId),
            ],
            'ingredients.*.actual_quantity' => ['required', 'numeric', 'min:0'],
            'packaging' => ['array'],
            'packaging.*.id' => [
                'required',
                'distinct:strict',
                Rule::exists('production_order_packaging_plan', 'id')
                    ->where('production_order_id', $scopedOrderId),
            ],
            'packaging.*.actual_units' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ingredients.required' => 'Debes registrar el consumo de ingredientes de la orden.',
            'ingredients.*.id.distinct' => 'No puedes repetir el mismo ingrediente en la lista.',
            'packaging.*.id.distinct' => 'No puedes repetir la misma presentación de empaque en la lista.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function consumptionAttributes(): array
    {
        return [
            'ingredients' => 'ingredientes consumidos',
            'ingredients.*.id' => 'ingrediente',
            'ingredients.*.actual_quantity' => 'cantidad real utilizada',
            'packaging' => 'empaques utilizados',
            'packaging.*.id' => 'plan de empaque',
            'packaging.*.actual_units' => 'unidades reales envasadas',
        ];
    }
}
