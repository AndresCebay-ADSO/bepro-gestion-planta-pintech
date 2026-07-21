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
            'agitation_start_time' => $this->parseOperationalTime($data['agitation_start_time'] ?? null),
            'agitation_end_time' => $this->parseOperationalTime($data['agitation_end_time'] ?? null),
            'packaging_start_time' => $this->parseOperationalTime($data['packaging_start_time'] ?? null),
            'packaging_end_time' => $this->parseOperationalTime($data['packaging_end_time'] ?? null),
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

    /**
     * Parsea un tiempo operacional enviado desde el frontend.
     *
     * Si el string incluye un offset explícito (Z, +HH:MM, -HH:MM), se respeta.
     * Si no trae offset, se asume que es hora local de Colombia (America/Bogota)
     * como fallback para clientes antiguos que aún no envían offset.
     */
    private function parseOperationalTime(?string $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        $hasOffset = preg_match('/[Zz]|[+-]\d{2}:\d{2}$/', $value) === 1;

        if ($hasOffset) {
            return Carbon::parse($value)->utc();
        }

        return Carbon::parse($value, 'America/Bogota')->utc();
    }
}
