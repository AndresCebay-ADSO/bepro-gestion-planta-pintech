<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Models\ProductionOrder;
use App\Support\OperationalTimes;
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
            'agitation_start_time' => OperationalTimes::parse($data['agitation_start_time'] ?? null),
            'agitation_end_time' => OperationalTimes::parse($data['agitation_end_time'] ?? null),
            'packaging_start_time' => OperationalTimes::parse($data['packaging_start_time'] ?? null),
            'packaging_end_time' => OperationalTimes::parse($data['packaging_end_time'] ?? null),
            'responsible_name' => $data['responsible_name'] ?? null,
            'spillage_quantity' => $data['spillage_quantity'] ?? 0,
            // Se guarda al enviar a revisión: sin esto, quien aprueba abría el saldo vacío y no podía cerrar la orden.
            'remnant_quantity_gallons' => $data['remnant_quantity_gallons'] ?? null,
            'remnant_notes' => $data['remnant_notes'] ?? null,
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

            $planUpdates = ['actual_units' => (string) $packData['actual_units']];

            // Empaque (3.7): solo lo que llega; vacío en envases o etiquetas usadas = tantos como unidades envasadas.
            foreach (['new_containers_used', 'labels_used', 'label_raw_material_id'] as $field) {
                if (array_key_exists($field, $packData)) {
                    $planUpdates[$field] = $packData[$field];
                }
            }

            // Sin etiqueta no hay etiquetas usadas: no se guarda una cantidad suelta que nada consumirá.
            if ((array_key_exists('label_raw_material_id', $planUpdates) ? $planUpdates['label_raw_material_id'] : $plan->label_raw_material_id) === null) {
                $planUpdates['labels_used'] = null;
            }

            $plan->update($planUpdates);
        }

        return $order->refresh();
    }
}
