<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Formula;
use App\Models\InventoryBatch;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\RawMaterial;
use App\Models\Warehouse;
use Illuminate\Validation\ValidationException;

class FifoStockAllocatorService
{
    public function __construct(
        private readonly InventoryBatchService $inventoryBatchService,
        private readonly InventoryMovementService $inventoryMovementService
    ) {}

    public function estimateMaterialUnitCostForPlanning(int $rawMaterialId, int $warehouseId, float $requiredQuantity): float
    {
        $unitCosts = $this->estimateMaterialUnitCostsForPlanning(
            warehouseId: $warehouseId,
            requirementsByMaterialId: [$rawMaterialId => $requiredQuantity]
        );

        return (float) ($unitCosts[$rawMaterialId] ?? 0.0);
    }

    /**
     * @param  array<int, float|int>  $requirementsByMaterialId
     * @return array<int, float>
     */
    public function estimateMaterialUnitCostsForPlanning(int $warehouseId, array $requirementsByMaterialId): array
    {
        if ($requirementsByMaterialId === []) {
            return [];
        }

        $requirements = collect($requirementsByMaterialId)
            ->mapWithKeys(fn (mixed $requiredQuantity, mixed $materialId): array => [(int) $materialId => (float) $requiredQuantity])
            ->filter(fn (float $requiredQuantity): bool => $requiredQuantity > 0);

        if ($requirements->isEmpty()) {
            return [];
        }

        $materialIds = $requirements->keys()->map(fn (mixed $materialId): int => (int) $materialId)->all();
        $batchesByMaterial = $this->inventoryBatchService
            ->availableForMaterials(
                warehouseId: $warehouseId,
                rawMaterialIds: $materialIds,
                columns: ['raw_material_id', 'remaining_quantity', 'unit_price']
            )
            ->groupBy('raw_material_id');

        $rawMaterials = RawMaterial::query()
            ->whereIn('id', $materialIds)
            ->get(['id', 'current_price', 'tracks_inventory'])
            ->keyBy('id');

        $estimatedUnitCosts = [];

        foreach ($requirements as $materialId => $requiredQuantity) {
            /** @var RawMaterial|null $rawMaterial */
            $rawMaterial = $rawMaterials->get($materialId);

            if ($rawMaterial !== null && ! $rawMaterial->tracks_inventory) {
                $estimatedUnitCosts[(int) $materialId] = (float) ($rawMaterial->current_price ?? 0);

                continue;
            }

            $batches = $batchesByMaterial->get($materialId, collect());
            $estimatedUnitCost = $this->estimateAverageUnitCostFromBatches((float) $requiredQuantity, $batches);

            if ($estimatedUnitCost <= 0) {
                $estimatedUnitCost = (float) ($rawMaterial?->current_price ?? 0);
            }

            $estimatedUnitCosts[(int) $materialId] = $estimatedUnitCost;
        }

        return $estimatedUnitCosts;
    }

    public function validateStockForOrder(Formula $formula, float $quantity, int $warehouseId): void
    {
        $warehouse = Warehouse::findOrFail($warehouseId);

        $requirements = [];
        foreach ($formula->details as $detail) {
            $materialId = (int) $detail->raw_material_id;
            $requirements[$materialId] = ($requirements[$materialId] ?? 0.0) + ((float) $detail->quantity * $quantity);
        }

        if ($requirements === []) {
            return;
        }

        $tracksInventoryByMaterialId = RawMaterial::query()
            ->whereIn('id', array_keys($requirements))
            ->pluck('tracks_inventory', 'id');

        $trackedMaterialIds = collect(array_keys($requirements))
            ->filter(fn (int $materialId): bool => (bool) ($tracksInventoryByMaterialId->get($materialId) ?? true))
            ->values()
            ->all();

        $availableByMaterialId = $this->inventoryBatchService->availableQuantitiesByMaterial(
            warehouseId: $warehouseId,
            rawMaterialIds: $trackedMaterialIds
        );

        foreach ($trackedMaterialIds as $materialId) {
            $required = (float) $requirements[$materialId];
            $available = (float) ($availableByMaterialId[$materialId] ?? 0.0);

            if ($available >= $required) {
                continue;
            }

            $materialCode = RawMaterial::query()->whereKey($materialId)->value('code') ?? (string) $materialId;

            throw ValidationException::withMessages([
                'product_id' => "Stock insuficiente de '{$materialCode}' en {$warehouse->name}. Requerido: {$required}, Disponible: {$available}.",
            ]);
        }
    }

    public function consumeProductionOrderDetail(
        ProductionOrder $order,
        ProductionOrderDetail $detail,
        float $requiredQuantity,
        int $userId
    ): float {
        return $this->consumeRawMaterialForProduction(
            order: $order,
            rawMaterialId: (int) $detail->raw_material_id,
            requiredQuantity: $requiredQuantity,
            userId: $userId,
            errorKey: 'ingredients'
        );
    }

    public function consumeRawMaterialForProduction(
        ProductionOrder $order,
        int $rawMaterialId,
        float $requiredQuantity,
        int $userId,
        string $errorKey,
        string $contextLabel = 'materia prima'
    ): float {
        if ($requiredQuantity <= 0) {
            return 0.0;
        }

        $remainingToConsume = $requiredQuantity;
        $totalConsumedCost = 0.0;

        /** @var RawMaterial|null $rawMaterial */
        $rawMaterial = RawMaterial::query()
            ->select(['id', 'code', 'current_price', 'tracks_inventory'])
            ->find($rawMaterialId);
        $materialCode = $rawMaterial?->code ?? (string) $rawMaterialId;

        if ($rawMaterial !== null && ! $rawMaterial->tracks_inventory) {
            $unitPrice = (float) ($rawMaterial->current_price ?? 0);

            $this->inventoryMovementService->recordProductionRawMaterialConsumption(
                order: $order,
                rawMaterialId: $rawMaterialId,
                batchId: null,
                quantity: $requiredQuantity,
                unitPrice: $unitPrice,
                userId: $userId,
                notes: "Consumo sin control de inventario en OP #{$order->order_number}"
            );

            return $requiredQuantity * $unitPrice;
        }

        $batches = $this->inventoryBatchService->availableForRawMaterial(
            rawMaterialId: $rawMaterialId,
            warehouseId: (int) $order->warehouse_id,
            lockForUpdate: true
        );

        foreach ($batches as $batch) {
            if ($remainingToConsume <= 0) {
                break;
            }

            $availableInBatch = (float) $batch->remaining_quantity;
            if ($availableInBatch <= 0) {
                continue;
            }

            $consumedQuantity = min($availableInBatch, $remainingToConsume);
            $unitPrice = (float) $batch->unit_price;

            $this->inventoryMovementService->recordProductionRawMaterialConsumption(
                order: $order,
                rawMaterialId: $rawMaterialId,
                batchId: (int) $batch->id,
                quantity: $consumedQuantity,
                unitPrice: $unitPrice,
                userId: $userId,
                notes: "Consumo FIFO en OP #{$order->order_number}"
            );

            $this->inventoryBatchService->decrementRemainingQuantity($batch, $consumedQuantity);
            $remainingToConsume -= $consumedQuantity;
            $totalConsumedCost += ($consumedQuantity * $unitPrice);
        }

        if ($remainingToConsume > 0) {
            throw ValidationException::withMessages([
                $errorKey => "Stock insuficiente de {$contextLabel} '{$materialCode}' en finalización. Requerido: {$requiredQuantity}, faltante: {$remainingToConsume}.",
            ]);
        }

        return $totalConsumedCost;
    }

    /**
     * @param  iterable<int, InventoryBatch>  $batches
     */
    private function estimateAverageUnitCostFromBatches(float $requiredQuantity, iterable $batches): float
    {
        if ($requiredQuantity <= 0) {
            return 0.0;
        }

        $remainingToEstimate = $requiredQuantity;
        $estimatedCost = 0.0;
        $estimatedQuantity = 0.0;

        foreach ($batches as $batch) {
            if ($remainingToEstimate <= 0) {
                break;
            }

            $availableInBatch = (float) $batch->remaining_quantity;
            if ($availableInBatch <= 0) {
                continue;
            }

            $quantityToEstimate = min($availableInBatch, $remainingToEstimate);
            $unitPrice = (float) $batch->unit_price;

            $estimatedCost += ($quantityToEstimate * $unitPrice);
            $estimatedQuantity += $quantityToEstimate;
            $remainingToEstimate -= $quantityToEstimate;
        }

        if ($estimatedQuantity > 0) {
            return $estimatedCost / $estimatedQuantity;
        }

        return 0.0;
    }
}
