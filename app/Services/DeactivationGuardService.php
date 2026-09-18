<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductionOrderStatus;
use App\Models\FinishedInventory;
use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductVariant;
use App\Models\Warehouse;

/**
 * Reglas que impiden desactivar un dato maestro mientras algo en curso lo necesita (docs/POLITICA_ELIMINACION.md §3.1).
 *
 * Las fórmulas no tienen regla: se desactivan al activar otra versión, y una orden en curso no depende de que su
 * fórmula siga activa (conserva sus propios detalles).
 *
 * Cada método devuelve el motivo del bloqueo, listo para mostrar, o `null` si se puede desactivar. Reactivar nunca se
 * bloquea.
 */
class DeactivationGuardService
{
    public function productBlocker(Product $product): ?string
    {
        if ($this->hasOpenOrders(['product_id' => $product->id])) {
            return __('No se puede desactivar el producto: tiene órdenes de producción en curso.');
        }

        return null;
    }

    public function variantBlocker(ProductVariant $variant): ?string
    {
        $hasStock = FinishedInventory::query()
            ->where('product_variant_id', $variant->id)
            ->where('quantity', '>', 0)
            ->exists();

        if ($hasStock) {
            return __('No se puede desactivar la presentación: tiene stock de producto terminado.');
        }

        return null;
    }

    public function warehouseBlocker(Warehouse $warehouse): ?string
    {
        $hasRawMaterialStock = InventoryBatch::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('remaining_quantity', '>', 0)
            ->exists();

        $hasFinishedStock = FinishedInventory::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('quantity', '>', 0)
            ->exists();

        if ($hasRawMaterialStock || $hasFinishedStock) {
            return __('No se puede desactivar la bodega: tiene stock de materia prima o de producto terminado.');
        }

        if ($this->hasOpenOrders(['warehouse_id' => $warehouse->id])) {
            return __('No se puede desactivar la bodega: tiene órdenes de producción en curso.');
        }

        return null;
    }

    /**
     * @param  array<string, int>  $conditions
     */
    private function hasOpenOrders(array $conditions): bool
    {
        return ProductionOrder::query()
            ->where($conditions)
            ->whereIn('status', ProductionOrderStatus::open())
            ->exists();
    }
}
