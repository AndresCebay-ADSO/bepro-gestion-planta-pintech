<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Formula;
use App\Models\InventoryBatch;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\RawMaterial;
use App\Models\Warehouse;
use App\Services\DecimalCalculator;
use Illuminate\Validation\ValidationException;

class FifoStockAllocatorService
{
    public function __construct(
        private readonly InventoryBatchService $inventoryBatchService,
        private readonly InventoryMovementService $inventoryMovementService,
        private readonly DecimalCalculator $calculator
    ) {}

    public function estimateMaterialUnitCostForPlanning(int $rawMaterialId, int $warehouseId, float|string $requiredQuantity): string
    {
        $unitCosts = $this->estimateMaterialUnitCostsForPlanning(
            warehouseId: $warehouseId,
            requirementsByMaterialId: [$rawMaterialId => $requiredQuantity]
        );

        return (string) ($unitCosts[$rawMaterialId] ?? '0');
    }

    /**
     * @param  array<int, float|int|string>  $requirementsByMaterialId
     * @return array<int, string>
     */
    public function estimateMaterialUnitCostsForPlanning(int $warehouseId, array $requirementsByMaterialId): array
    {
        if ($requirementsByMaterialId === []) {
            return [];
        }

        $requirements = collect($requirementsByMaterialId)
            ->mapWithKeys(fn (mixed $requiredQuantity, mixed $materialId): array => [(int) $materialId => (string) $requiredQuantity])
            ->filter(fn (string $requiredQuantity): bool => ! $this->calculator->isZero($requiredQuantity));

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
                $estimatedUnitCosts[(int) $materialId] = (string) ($rawMaterial->current_price ?? '0');

                continue;
            }

            $batches = $batchesByMaterial->get($materialId, collect());
            $estimatedUnitCost = $this->estimateAverageUnitCostFromBatches($requiredQuantity, $batches);

            if ($this->calculator->cmp($estimatedUnitCost, '0') <= 0) {
                $estimatedUnitCost = (string) ($rawMaterial?->current_price ?? '0');
            }

            $estimatedUnitCosts[(int) $materialId] = $estimatedUnitCost;
        }

        return $estimatedUnitCosts;
    }

    public function validateStockForOrder(Formula $formula, float|string $quantity, int $warehouseId): void
    {
        $warehouse = Warehouse::findOrFail($warehouseId);
        $qty = (string) $quantity;

        $requirements = [];
        foreach ($formula->details as $detail) {
            $materialId = (int) $detail->raw_material_id;
            $product = $this->calculator->mul((string) $detail->quantity, $qty, 4);
            $current = (string) ($requirements[$materialId] ?? '0');
            $requirements[$materialId] = $this->calculator->add($current, $product, 4);
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
            $required = $requirements[$materialId];
            $available = (string) ($availableByMaterialId[$materialId] ?? '0');

            if ($this->calculator->cmp($available, $required) >= 0) {
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
        float|string $requiredQuantity,
        int $userId
    ): string {
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
        float|string $requiredQuantity,
        int $userId,
        string $errorKey,
        string $contextLabel = 'materia prima'
    ): string {
        $requiredQtyStr = (string) $requiredQuantity;

        if ($this->calculator->cmp($requiredQtyStr, '0') <= 0) {
            return '0';
        }

        $remainingToConsume = $requiredQtyStr;
        $totalConsumedCost = '0';

        /** @var RawMaterial|null $rawMaterial */
        $rawMaterial = RawMaterial::query()
            ->select(['id', 'code', 'current_price', 'tracks_inventory'])
            ->find($rawMaterialId);
        $materialCode = $rawMaterial?->code ?? (string) $rawMaterialId;

        if ($rawMaterial !== null && ! $rawMaterial->tracks_inventory) {
            $unitPrice = (string) ($rawMaterial->current_price ?? '0');

            $this->inventoryMovementService->recordProductionRawMaterialConsumption(
                order: $order,
                rawMaterialId: $rawMaterialId,
                batchId: null,
                quantity: $requiredQtyStr,
                unitPrice: $unitPrice,
                userId: $userId,
                notes: "Consumo sin control de inventario en OP #{$order->order_number}"
            );

            return $this->calculator->mul($requiredQtyStr, $unitPrice, 4);
        }

        $batches = $this->inventoryBatchService->availableForRawMaterial(
            rawMaterialId: $rawMaterialId,
            warehouseId: (int) $order->warehouse_id,
            lockForUpdate: true
        );

        foreach ($batches as $batch) {
            if ($this->calculator->cmp($remainingToConsume, '0') <= 0) {
                break;
            }

            $availableInBatch = (string) $batch->remaining_quantity;
            if ($this->calculator->cmp($availableInBatch, '0') <= 0) {
                continue;
            }

            $consumedQuantity = $this->calculator->min($availableInBatch, $remainingToConsume, 4);
            $unitPrice = (string) $batch->unit_price;

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
            $remainingToConsume = $this->calculator->sub($remainingToConsume, $consumedQuantity, 4);
            $batchCost = $this->calculator->mul($consumedQuantity, $unitPrice, 4);
            $totalConsumedCost = $this->calculator->add($totalConsumedCost, $batchCost, 4);
        }

        if ($this->calculator->cmp($remainingToConsume, '0') > 0) {
            throw ValidationException::withMessages([
                $errorKey => "Stock insuficiente de {$contextLabel} '{$materialCode}' en finalización. Requerido: {$requiredQtyStr}, faltante: {$remainingToConsume}.",
            ]);
        }

        return $totalConsumedCost;
    }

    /**
     * @param  iterable<int, InventoryBatch>  $batches
     */
    private function estimateAverageUnitCostFromBatches(string|float $requiredQuantity, iterable $batches): string
    {
        $requiredQtyStr = (string) $requiredQuantity;

        if ($this->calculator->cmp($requiredQtyStr, '0') <= 0) {
            return '0';
        }

        $remainingToEstimate = $this->calculator->round($requiredQtyStr, 4);
        $estimatedCost = '0';
        $estimatedQuantity = '0';

        foreach ($batches as $batch) {
            if ($this->calculator->isZero($remainingToEstimate)) {
                break;
            }

            $availableInBatch = (string) $batch->remaining_quantity;
            if ($this->calculator->isZero($availableInBatch)) {
                continue;
            }

            $quantityToEstimate = $this->calculator->min($availableInBatch, $remainingToEstimate, 4);
            $unitPrice = (string) $batch->unit_price;

            $itemCost = $this->calculator->mul($quantityToEstimate, $unitPrice, 4);
            $estimatedCost = $this->calculator->add($estimatedCost, $itemCost, 4);
            $estimatedQuantity = $this->calculator->add($estimatedQuantity, $quantityToEstimate, 4);
            $remainingToEstimate = $this->calculator->sub($remainingToEstimate, $quantityToEstimate, 4);
        }

        if (! $this->calculator->isZero($estimatedQuantity)) {
            return $this->calculator->div($estimatedCost, $estimatedQuantity, 4);
        }

        return '0';
    }
}
