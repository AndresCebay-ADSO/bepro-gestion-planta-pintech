<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductionOrderStatus;
use App\Enums\RemnantStatus;
use App\Models\FinishedInventory;
use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderPackagingPlan;
use App\Models\ProductionRemnant;
use App\Models\ProductVariant;
use App\Models\Warehouse;

/**
 * Reglas que impiden desactivar un dato maestro mientras algo en curso lo necesita (docs/POLITICA_ELIMINACION.md §3.1):
 * no se desactiva lo que tiene valor en libros (stock, saldos de producción) ni lo que una orden en curso va a usar,
 * porque después ningún selector lo ofrecería y ese valor quedaría atrapado.
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

        // Desactivar el producto oculta todas sus presentaciones en los pedidos: su stock ya no se podría vender.
        if ($this->hasFinishedStock(['product_id' => $product->id])) {
            return __('No se puede desactivar el producto: tiene stock de producto terminado.');
        }

        return null;
    }

    public function variantBlocker(ProductVariant $variant): ?string
    {
        if ($this->hasFinishedStock(['product_variant_id' => $variant->id])) {
            return __('No se puede desactivar la presentación: tiene stock de producto terminado.');
        }

        // Al terminar, la orden ingresaría su producto terminado en una presentación inactiva.
        $plannedInOpenOrder = ProductionOrderPackagingPlan::query()
            ->where('product_variant_id', $variant->id)
            ->whereHas('productionOrder', fn ($query) => $query->whereIn('status', ProductionOrderStatus::open()))
            ->exists();

        if ($plannedInOpenOrder) {
            return __('No se puede desactivar la presentación: una orden de producción en curso la va a envasar.');
        }

        return null;
    }

    public function warehouseBlocker(Warehouse $warehouse): ?string
    {
        $hasRawMaterialStock = InventoryBatch::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('remaining_quantity', '>', 0)
            ->exists();

        if ($hasRawMaterialStock || $this->hasFinishedStock(['warehouse_id' => $warehouse->id])) {
            return __('No se puede desactivar la bodega: tiene stock de materia prima o de producto terminado.');
        }

        // Los saldos solo se consumen en órdenes de su misma bodega, y una bodega inactiva no recibe órdenes nuevas.
        $hasAvailableRemnants = ProductionRemnant::query()
            ->where('warehouse_id', $warehouse->id)
            ->whereIn('status', [RemnantStatus::Available, RemnantStatus::PartiallyConsumed])
            ->where('available_quantity_gallons', '>', 0)
            ->exists();

        if ($hasAvailableRemnants) {
            return __('No se puede desactivar la bodega: tiene saldos de producción disponibles.');
        }

        if ($this->hasOpenOrders(['warehouse_id' => $warehouse->id])) {
            return __('No se puede desactivar la bodega: tiene órdenes de producción en curso.');
        }

        return null;
    }

    /**
     * @param  array<string, int>  $conditions
     */
    private function hasFinishedStock(array $conditions): bool
    {
        return FinishedInventory::query()
            ->where($conditions)
            ->where('quantity', '>', 0)
            ->exists();
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
