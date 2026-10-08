<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProductionOrder;
use App\Models\ProductionRemnant;

/**
 * Saldos que se pueden consumir en una orden: los disponibles de su bodega, del más antiguo al más nuevo (FIFO,
 * desempatado por id: con la misma fecha, el orden y el corte de los 50 serían arbitrarios). Una sola fuente para la
 * lista que llega con el detalle de la orden y la que la refresca (`RemnantConsumptionController::availableRemnants`).
 *
 * Un saldo puede venir de cualquier producto de la bodega (decisión del 2026-09-23), y al consumirlo se mezcla en otro
 * lote: cada uno lleva el producto con el color y el lote de su orden de origen, para que quien lo elige sepa qué es
 * (B57).
 */
class AvailableRemnantsService
{
    /**
     * @return list<array{id: int, source_order_number: string, source_lot_number: int, product_name: string, available_quantity_gallons: float, density_kg_per_gallon: float}>
     */
    public function forOrder(ProductionOrder $order): array
    {
        return ProductionRemnant::query()
            ->with(['sourceOrder:id,order_number,lot_number,color', 'product:id,name'])
            ->available()
            ->where('warehouse_id', $order->warehouse_id)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->limit(50)
            ->get()
            ->map(fn (ProductionRemnant $remnant): array => [
                'id' => $remnant->id,
                'source_order_number' => $remnant->sourceOrder->order_number,
                'source_lot_number' => $remnant->sourceOrder->lot_number,
                'product_name' => ProductionOrder::nameWithColor($remnant->product->name, $remnant->sourceOrder->color),
                'available_quantity_gallons' => (float) $remnant->available_quantity_gallons,
                'density_kg_per_gallon' => (float) $remnant->density_kg_per_gallon,
            ])
            ->values()
            ->all();
    }
}
