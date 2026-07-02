<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Models\ProductionOrder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SaveProductionOrderOperationalDataAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(ProductionOrder $order, array $data): ProductionOrder
    {
        $order->update([
            'actual_quantity' => $data['actual_yield_quantity'] ?? $order->quantity,
            'viscosity_ku' => $data['viscosity_ku'] ?? null,
            'grinding_hg' => $data['grinding_hg'] ?? null,
            'quality_solids' => $data['quality_solids'] ?? null,
            'agitation_start_time' => isset($data['agitation_start_time']) ? Carbon::parse($data['agitation_start_time'], 'America/Bogota') : null,
            'agitation_end_time' => isset($data['agitation_end_time']) ? Carbon::parse($data['agitation_end_time'], 'America/Bogota') : null,
            'packaging_start_time' => isset($data['packaging_start_time']) ? Carbon::parse($data['packaging_start_time'], 'America/Bogota') : null,
            'packaging_end_time' => isset($data['packaging_end_time']) ? Carbon::parse($data['packaging_end_time'], 'America/Bogota') : null,
            'responsible_name' => $data['responsible_name'] ?? null,
            'spillage_quantity' => $data['spillage_quantity'] ?? 0,
            'density_kg_per_gallon' => $data['density_kg_per_gallon'] ?? $order->density_kg_per_gallon,
            'notes' => $data['notes'] ?? $order->notes,
        ]);

        $order->loadMissing(['details', 'packagingPlans']);
        $detailsById = $order->details->keyBy('id');
        $packagingPlansById = $order->packagingPlans->keyBy('id');

        foreach ($data['ingredients'] as $ingredientData) {
            $detail = $detailsById->get((int) $ingredientData['id']);
            if ($detail === null) {
                throw ValidationException::withMessages([
                    'ingredients' => __('Uno de los ingredientes no pertenece a la orden de producción seleccionada.'),
                ]);
            }

            $detail->update([
                'actual_quantity' => (string) $ingredientData['actual_quantity'],
            ]);
        }

        foreach (($data['packaging'] ?? []) as $packData) {
            $plan = $packagingPlansById->get((int) $packData['id']);
            if ($plan === null) {
                throw ValidationException::withMessages([
                    'packaging' => __('Uno de los planes de envasado no pertenece a la orden de producción seleccionada.'),
                ]);
            }

            $plan->update([
                'actual_units' => (string) $packData['actual_units'],
            ]);
        }

        return $order->refresh();
    }
}
