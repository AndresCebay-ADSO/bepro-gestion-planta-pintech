<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\InventoryBatch;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\RawMaterial;
use App\Services\DecimalCalculator;
use App\Support\NumberFormat;
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
            $estimatedUnitCost = $this->estimateUnitCostFromBatches($requiredQuantity, $batches, (string) ($rawMaterial?->current_price ?? '0'));

            if ($this->calculator->cmp($estimatedUnitCost, '0') <= 0) {
                $estimatedUnitCost = (string) ($rawMaterial?->current_price ?? '0');
            }

            $estimatedUnitCosts[(int) $materialId] = $estimatedUnitCost;
        }

        return $estimatedUnitCosts;
    }

    /**
     * Lo que falta en la bodega para cubrir esos requerimientos, en la unidad de cada materia prima y en el mismo orden. Una
     * materia prima sin control de inventario no tiene stock que pueda faltar.
     *
     * @param  array<int, string>  $requirementsByMaterialId
     * @return list<array{code: string, unit: string, required: string, available: string, missing: string, has_reference_price: bool}>
     */
    public function stockShortages(array $requirementsByMaterialId, int $warehouseId): array
    {
        $materials = RawMaterial::query()
            ->with('unitOfMeasure')
            ->whereIn('id', array_keys($requirementsByMaterialId))
            ->where('tracks_inventory', true)
            ->get()
            ->keyBy('id');

        $availableByMaterialId = $this->inventoryBatchService->availableQuantitiesByMaterial(
            warehouseId: $warehouseId,
            rawMaterialIds: $materials->keys()->all()
        );

        $shortages = [];

        foreach ($requirementsByMaterialId as $materialId => $required) {
            /** @var RawMaterial|null $material */
            $material = $materials->get($materialId);

            if ($material === null) {
                continue;
            }

            $available = $this->calculator->round((string) ($availableByMaterialId[$materialId] ?? '0'), 4);

            if ($this->calculator->cmp($available, $required) >= 0) {
                continue;
            }

            $shortages[] = [
                'code' => (string) $material->code,
                'unit' => (string) $material->unitOfMeasure->symbol,
                'required' => $required,
                'available' => $available,
                'missing' => $this->calculator->sub($required, $available, 4),
                'has_reference_price' => $this->calculator->isPositive((string) ($material->current_price ?? '0')),
            ];
        }

        return $shortages;
    }

    public function consumeProductionOrderDetail(
        ProductionOrder $order,
        ProductionOrderDetail $detail,
        float|string $requiredQuantity,
        int $userId,
        ?string $movementDate = null,
    ): string {
        return $this->consumeRawMaterialForProduction(
            order: $order,
            rawMaterialId: (int) $detail->raw_material_id,
            requiredQuantity: $requiredQuantity,
            userId: $userId,
            errorKey: 'ingredients',
            movementDate: $movementDate,
        );
    }

    public function consumeRawMaterialForProduction(
        ProductionOrder $order,
        int $rawMaterialId,
        float|string $requiredQuantity,
        int $userId,
        string $errorKey,
        string $contextLabel = 'materia prima',
        ?string $movementDate = null,
    ): string {
        return $this->consumeFromWarehouse(
            rawMaterialId: $rawMaterialId,
            warehouseId: (int) $order->warehouse_id,
            requiredQuantity: $requiredQuantity,
            userId: $userId,
            origin: ['production_order_id' => (int) $order->id],
            originLabel: "OP #{$order->order_number}",
            errorKey: $errorKey,
            contextLabel: $contextLabel,
            shortageMoment: 'en finalización',
            movementDate: $movementDate,
        );
    }

    /**
     * Consume una materia prima de una bodega y devuelve su costo total. Con control de inventario descuenta por FIFO,
     * lote por lote, al precio de cada lote; sin él (agua, etiquetas) registra el consumo al precio de referencia. Cada
     * salida queda como movimiento enlazado a su origen: una OP o un termoencogido (3.8).
     *
     * @param  array{production_order_id?: int, shrink_wrap_id?: int}  $origin
     */
    public function consumeFromWarehouse(
        int $rawMaterialId,
        int $warehouseId,
        float|string $requiredQuantity,
        int $userId,
        array $origin,
        string $originLabel,
        string $errorKey,
        string $contextLabel,
        string $shortageMoment,
        ?string $movementDate = null,
    ): string {
        $requiredQtyStr = (string) $requiredQuantity;

        if ($this->calculator->cmp($requiredQtyStr, '0') <= 0) {
            return '0';
        }

        $remainingToConsume = $requiredQtyStr;
        $totalConsumedCost = '0';

        /** @var RawMaterial|null $rawMaterial */
        $rawMaterial = RawMaterial::query()
            ->select(['id', 'code', 'current_price', 'tracks_inventory', 'unit_of_measure_id'])
            ->find($rawMaterialId);
        $materialCode = $rawMaterial?->code ?? (string) $rawMaterialId;

        if ($rawMaterial !== null && ! $rawMaterial->tracks_inventory) {
            $unitPrice = (string) ($rawMaterial->current_price ?? '0');

            $this->inventoryMovementService->recordConsumption(
                rawMaterialId: $rawMaterialId,
                warehouseId: $warehouseId,
                batchId: null,
                quantity: $requiredQtyStr,
                unitPrice: $unitPrice,
                userId: $userId,
                notes: "Consumo sin control de inventario en {$originLabel}",
                origin: $origin,
                movementDate: $movementDate,
            );

            return $this->calculator->mul($requiredQtyStr, $unitPrice, 4);
        }

        $batches = $this->inventoryBatchService->availableForRawMaterial(
            rawMaterialId: $rawMaterialId,
            warehouseId: $warehouseId,
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

            $this->inventoryMovementService->recordConsumption(
                rawMaterialId: $rawMaterialId,
                warehouseId: $warehouseId,
                batchId: (int) $batch->id,
                quantity: $consumedQuantity,
                unitPrice: $unitPrice,
                userId: $userId,
                notes: "Consumo FIFO en {$originLabel}",
                origin: $origin,
                movementDate: $movementDate,
            );

            $this->inventoryBatchService->decrementRemainingQuantity($batch, $consumedQuantity);
            $remainingToConsume = $this->calculator->sub($remainingToConsume, $consumedQuantity, 4);
            $batchCost = $this->calculator->mul($consumedQuantity, $unitPrice, 4);
            $totalConsumedCost = $this->calculator->add($totalConsumedCost, $batchCost, 4);
        }

        if ($this->calculator->cmp($remainingToConsume, '0') > 0) {
            $unit = $rawMaterial?->unitOfMeasure?->symbol;
            $required = trim(NumberFormat::quantity($requiredQtyStr)." {$unit}");
            $missing = trim(NumberFormat::quantity($remainingToConsume)." {$unit}");

            throw ValidationException::withMessages([
                $errorKey => "Stock insuficiente de {$contextLabel} '{$materialCode}' {$shortageMoment}. Requerido: {$required}, faltante: {$missing}.",
            ]);
        }

        return $totalConsumedCost;
    }

    /**
     * El costo por unidad de esa cantidad: los lotes en orden FIFO, cada uno a su precio, y lo que no alcancen, al precio de
     * referencia. Si solo se promediaran los lotes, 1 kg barato costearía los 20 kg que pide la orden. Sin precio de
     * referencia, lo que falta no suma, y el formulario de la orden lo avisa.
     *
     * @param  iterable<int, InventoryBatch>  $batches
     */
    private function estimateUnitCostFromBatches(string|float $requiredQuantity, iterable $batches, string $referencePrice): string
    {
        $required = $this->calculator->round((string) $requiredQuantity, 4);

        if ($this->calculator->cmp($required, '0') <= 0) {
            return '0';
        }

        $remainingToEstimate = $required;
        $estimatedCost = '0';

        foreach ($batches as $batch) {
            if ($this->calculator->isZero($remainingToEstimate)) {
                break;
            }

            $availableInBatch = (string) $batch->remaining_quantity;
            if ($this->calculator->isZero($availableInBatch)) {
                continue;
            }

            $quantityToEstimate = $this->calculator->min($availableInBatch, $remainingToEstimate, 4);
            $itemCost = $this->calculator->mul($quantityToEstimate, (string) $batch->unit_price, 4);
            $estimatedCost = $this->calculator->add($estimatedCost, $itemCost, 4);
            $remainingToEstimate = $this->calculator->sub($remainingToEstimate, $quantityToEstimate, 4);
        }

        $estimatedCost = $this->calculator->add($estimatedCost, $this->calculator->mul($remainingToEstimate, $referencePrice, 4), 4);

        return $this->calculator->div($estimatedCost, $required, 4);
    }
}
