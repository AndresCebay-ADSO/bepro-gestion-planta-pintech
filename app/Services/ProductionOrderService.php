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
use App\Models\ProductVariant;
use App\Models\RawMaterial;
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

                $this->consumeRawMaterialFifo($order, $detail, $actualQuantity, $userId);
            }

            // 3. Procesar Entrada de Producto Terminado (Packaging Plan)
            foreach (($data['packaging'] ?? []) as $packData) {
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
                        'warehouse_id' => $order->warehouse_id,
                    ]);

                    if (! $inventory->exists && $inventory->product_variant_id === null) {
                        $inventory->product_variant_id = $plan->product_variant_id;
                    }

                    $inventory->quantity = ($inventory->quantity ?? 0) + $actualUnits;
                    $inventory->save();

                    $variant = ProductVariant::query()
                        ->select(['id', 'package_raw_material_id'])
                        ->find($plan->product_variant_id);

                    if ($variant?->package_raw_material_id !== null) {
                        $this->consumeRawMaterialFifoByMaterialId(
                            order: $order,
                            rawMaterialId: (int) $variant->package_raw_material_id,
                            requiredQuantity: $actualUnits,
                            userId: $userId,
                            errorKey: 'packaging',
                            contextLabel: 'envase'
                        );
                    }
                }
            }

            // 4. Calcular Porcentaje de Rendimiento
            // (Opcional, basado en la lógica de negocio final)
            // $plannedInput = $order->quantity;
            // $order->update(['yield_percentage' => ($actualOutput / $plannedInput) * 100]);

            return $order->refresh();
        });
    }

    private function consumeRawMaterialFifo(ProductionOrder $order, ProductionOrderDetail $detail, float $requiredQuantity, int $userId): void
    {
        $this->consumeRawMaterialFifoByMaterialId(
            order: $order,
            rawMaterialId: $detail->raw_material_id,
            requiredQuantity: $requiredQuantity,
            userId: $userId,
            errorKey: 'ingredients'
        );
    }

    private function consumeRawMaterialFifoByMaterialId(
        ProductionOrder $order,
        int $rawMaterialId,
        float $requiredQuantity,
        int $userId,
        string $errorKey,
        string $contextLabel = 'materia prima'
    ): void {
        if ($requiredQuantity <= 0) {
            return;
        }

        $remainingToConsume = $requiredQuantity;

        $batches = InventoryBatch::query()
            ->where('raw_material_id', $rawMaterialId)
            ->where('warehouse_id', $order->warehouse_id)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('entry_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        /** @var RawMaterial|null $rawMaterial */
        $rawMaterial = RawMaterial::query()->find($rawMaterialId);
        $materialCode = $rawMaterial?->code ?? (string) $rawMaterialId;

        foreach ($batches as $batch) {
            if ($remainingToConsume <= 0) {
                break;
            }

            $availableInBatch = (float) $batch->remaining_quantity;
            if ($availableInBatch <= 0) {
                continue;
            }

            $consumedQuantity = min($availableInBatch, $remainingToConsume);

            InventoryMovement::create([
                'raw_material_id' => $rawMaterialId,
                'warehouse_id' => $order->warehouse_id,
                'batch_id' => $batch->id,
                'production_order_id' => $order->id,
                'type' => InventoryMovementType::Exit,
                'quantity' => $consumedQuantity,
                'cost_price' => $batch->unit_price,
                'movement_date' => now(),
                'notes' => "Consumo FIFO en OP #{$order->order_number}",
                'created_by' => $userId,
            ]);

            $batch->decrement('remaining_quantity', $consumedQuantity);
            $remainingToConsume -= $consumedQuantity;
        }

        if ($remainingToConsume > 0) {
            throw ValidationException::withMessages([
                $errorKey => "Stock insuficiente de {$contextLabel} '{$materialCode}' en finalización. Requerido: {$requiredQuantity}, faltante: {$remainingToConsume}.",
            ]);
        }
    }
}
