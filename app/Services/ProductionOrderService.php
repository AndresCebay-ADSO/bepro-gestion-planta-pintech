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
use App\Models\ProductionCost;
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

            // 2.5. Calcular costos distribuidos por variante
            $distributedCosts = $this->calculateDistributedCosts($order, $data['packaging'] ?? []);

            // 3. Procesar Entrada de Producto Terminado (Packaging Plan)
            foreach (($data['packaging'] ?? []) as $packData) {
                $plan = ProductionOrderPackagingPlan::findOrFail($packData['id']);
                $actualUnits = (float) $packData['actual_units'];

                $plan->update(['actual_units' => $actualUnits]);

                if ($actualUnits > 0) {
                    $costPriceForVariant = $distributedCosts[$plan->product_variant_id] ?? 0;

                    // Registrar entrada de producto terminado
                    FinishedInventoryMovement::create([
                        'product_id' => $order->product_id,
                        'product_variant_id' => $plan->product_variant_id,
                        'warehouse_id' => $order->warehouse_id,
                        'production_order_id' => $order->id,
                        'type' => InventoryMovementType::Entry,
                        'quantity' => $actualUnits,
                        'cost_price' => $costPriceForVariant,
                        'movement_date' => now(),
                        'notes' => "Finalización OP #{$order->order_number}",
                        'created_by' => $userId,
                    ]);

                    // Actualizar ProductVariant.current_cost con el costo calculado
                    ProductVariant::where('id', $plan->product_variant_id)
                        ->update(['current_cost' => $costPriceForVariant]);

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

            // 4. Crear/actualizar ProductionCost con el costo total del granel
            $totalBulkCost = InventoryMovement::query()
                ->where('production_order_id', $order->id)
                ->where('type', InventoryMovementType::Exit)
                ->sum(DB::raw('quantity * cost_price'));

            if ($totalBulkCost > 0) {
                ProductionCost::updateOrCreate(
                    [
                        'product_id' => $order->product_id,
                        'formula_id' => $order->formula_id,
                    ],
                    [
                        'cost' => $totalBulkCost,
                        'calculated_at' => now(),
                    ]
                );
            }

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

    /**
     * Calculate distributed costs for each variant based on bulk cost and presentation_value.
     *
     * @param  array  $packagingData  Array of packaging plan data with 'id' and 'actual_units'
     * @return array Array keyed by product_variant_id with cost_price for each variant
     */
    private function calculateDistributedCosts(ProductionOrder $order, array $packagingData): array
    {
        if (empty($packagingData)) {
            return [];
        }

        // Calculate total bulk cost (sum of all consumed ingredients with their costs)
        $totalBulkCost = (float) InventoryMovement::query()
            ->where('production_order_id', $order->id)
            ->where('type', InventoryMovementType::Exit)
            ->sum(DB::raw('quantity * cost_price'));

        if ($totalBulkCost <= 0) {
            return [];
        }

        $packagingDataMap = []; // Map packaging plan id to data

        foreach ($packagingData as $packData) {
            $packagingDataMap[$packData['id']] = $packData;
        }

        // Get all packaging plans with their variants first
        $plans = ProductionOrderPackagingPlan::query()
            ->where('production_order_id', $order->id)
            ->with('productVariant')
            ->get();

        // Calculate total rendimiento (sum of actual_units * presentation_value from all packaging plans)
        $totalRendimiento = 0;
        foreach ($plans as $plan) {
            if (! isset($packagingDataMap[$plan->id])) {
                continue;
            }

            $variant = $plan->productVariant;
            if (! $variant) {
                continue;
            }

            $actualUnits = (float) ($packagingDataMap[$plan->id]['actual_units'] ?? 0);
            if ($actualUnits <= 0) {
                continue;
            }

            $presentationValue = (float) ($variant->presentation_value ?? 1);
            $totalRendimiento += $actualUnits * $presentationValue;
        }

        if ($totalRendimiento <= 0) {
            return [];
        }

        // Cost per unit of rendimiento (bulk)
        $costPerUnitBulk = $totalBulkCost / $totalRendimiento;

        $distributedCosts = [];

        // Distribute cost to each variant based on its presentation_value
        foreach ($plans as $plan) {
            if (! isset($packagingDataMap[$plan->id])) {
                continue;
            }

            $variant = $plan->productVariant;
            if (! $variant) {
                continue;
            }

            $actualUnits = (float) ($packagingDataMap[$plan->id]['actual_units'] ?? 0);
            if ($actualUnits <= 0) {
                continue;
            }

            // Cost distributed for this variant: (cost_per_unit_bulk) * presentation_value
            $presentationValue = (float) ($variant->presentation_value ?? 1);
            $costWithPresentation = $costPerUnitBulk * $presentationValue;

            // Add packaging material cost if exists
            $packagingCost = 0;
            if ($variant->package_raw_material_id !== null) {
                $packagingCost = (float) InventoryBatch::query()
                    ->where('raw_material_id', $variant->package_raw_material_id)
                    ->orderBy('entry_date')
                    ->value('unit_price') ?? 0;
            }

            $finalCostPrice = $costWithPresentation + $packagingCost;
            $distributedCosts[$variant->id] = $finalCostPrice;
        }

        return $distributedCosts;
    }
}
