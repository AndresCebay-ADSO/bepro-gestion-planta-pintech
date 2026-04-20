<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Enums\ProductionOrderStatus;
use App\Models\FinishedInventory;
use App\Models\FinishedInventoryMovement;
use App\Models\Formula;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\ProductionOrderPackagingPlan;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionOrderService
{
    /**
     * Validar si hay stock suficiente en la bodega seleccionada para producir una cantidad específica.
     */
    public function validateStockForOrder(Formula $formula, float $quantity, int $warehouseId): void
    {
        $warehouse = Warehouse::findOrFail($warehouseId);

        foreach ($formula->details as $detail) {
            $required = $detail->quantity * $quantity;

            $available = InventoryBatch::where('raw_material_id', $detail->raw_material_id)
                ->where('warehouse_id', $warehouseId)
                ->sum('remaining_quantity');

            if ($available < $required) {
                $materialCode = $detail->rawMaterial->code;
                throw ValidationException::withMessages([
                    'product_id' => "Stock insuficiente de '{$materialCode}' en {$warehouse->name}. Requerido: {$required}, Disponible: {$available}.",
                ]);
            }
        }
    }

    /**
     * Cerrar una orden de producción, procesando el consumo real y la entrada de producto terminado.
     */
    public function completeOrder(ProductionOrder $order, array $data): ProductionOrder
    {
        return DB::transaction(function () use ($order, $data) {
            if ($order->status === ProductionOrderStatus::Completed) {
                throw new \DomainException('La orden ya ha sido completada.');
            }

            $userId = auth()->id() ?? throw new \RuntimeException('No authenticated user');

            // 1. Actualizar metadatos operacionales de la orden
            $order->update([
                'status' => ProductionOrderStatus::Completed,
                'completion_date' => now(),
                'actual_quantity' => $data['actual_yield_quantity'] ?? $order->quantity,
                'viscosity_ku' => $data['viscosity_ku'] ?? null,
                'grinding_hg' => $data['grinding_hg'] ?? null,
                'agitation_start_time' => $data['agitation_start_time'] ?? null,
                'agitation_end_time' => $data['agitation_end_time'] ?? null,
                'packaging_start_time' => $data['packaging_start_time'] ?? null,
                'packaging_end_time' => $data['packaging_end_time'] ?? null,
                'responsible_name' => $data['responsible_name'] ?? null,
                'spillage_quantity' => $data['spillage_quantity'] ?? 0,
                'notes' => $data['notes'] ?? $order->notes,
            ]);

            // 2. Procesar Consumo de Materias Primas (Detalles)
            foreach ($data['ingredients'] as $ingredientData) {
                $detail = ProductionOrderDetail::findOrFail($ingredientData['id']);
                $actualQuantity = (float) $ingredientData['actual_quantity'];

                $detail->update(['actual_quantity' => $actualQuantity]);

                // Registrar salida de inventario
                InventoryMovement::create([
                    'raw_material_id' => $detail->raw_material_id,
                    'warehouse_id' => $order->warehouse_id,
                    'batch_id' => $detail->batch_id,
                    'production_order_id' => $order->id,
                    'type' => InventoryMovementType::Exit,
                    'quantity' => $actualQuantity,
                    'cost_price' => $detail->unit_cost,
                    'movement_date' => now(),
                    'notes' => "Consumo en OP #{$order->order_number}",
                    'created_by' => $userId,
                ]);

                // Descontar del lote
                $batch = $detail->batch;
                $batch->decrement('remaining_quantity', $actualQuantity);
            }

            // 3. Procesar Entrada de Producto Terminado (Packaging Plan)
            foreach ($data['packaging'] as $packData) {
                $plan = ProductionOrderPackagingPlan::findOrFail($packData['id']);
                $actualUnits = (float) $packData['actual_units'];

                $plan->update(['actual_units' => $actualUnits]);

                if ($actualUnits > 0) {
                    // Registrar entrada de producto terminado
                    FinishedInventoryMovement::create([
                        'product_id' => $order->product_id,
                        'product_variant_id' => $plan->product_variant_id,
                        'warehouse_id' => $order->warehouse_id,
                        'production_order_id' => $order->id,
                        'type' => InventoryMovementType::Entry,
                        'quantity' => $actualUnits,
                        'movement_date' => now(),
                        'notes' => "Finalización OP #{$order->order_number}",
                        'created_by' => $userId,
                    ]);

                    // Actualizar o crear registro en FinishedInventory
                    $inventory = FinishedInventory::firstOrNew([
                        'product_id' => $order->product_id,
                        'product_variant_id' => $plan->product_variant_id,
                        'warehouse_id' => $order->warehouse_id,
                    ]);

                    $inventory->quantity = ($inventory->quantity ?? 0) + $actualUnits;
                    $inventory->save();
                }
            }

            // 4. Calcular Porcentaje de Rendimiento
            // (Opcional, basado en la lógica de negocio final)
            // $plannedInput = $order->quantity;
            // $order->update(['yield_percentage' => ($actualOutput / $plannedInput) * 100]);

            return $order->refresh();
        });
    }
}
