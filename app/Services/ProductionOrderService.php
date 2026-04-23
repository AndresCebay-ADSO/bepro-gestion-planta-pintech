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
     * Estimar costo unitario (sin consumir inventario) usando FIFO de lotes disponibles.
     */
    public function estimateMaterialUnitCostForPlanning(int $rawMaterialId, int $warehouseId, float $requiredQuantity): float
    {
        $unitCosts = $this->estimateMaterialUnitCostsForPlanning(
            warehouseId: $warehouseId,
            requirementsByMaterialId: [$rawMaterialId => $requiredQuantity]
        );

        return (float) ($unitCosts[$rawMaterialId] ?? 0.0);
    }

    /**
     * Estimar costos unitarios por materia prima (sin consumir inventario) usando FIFO.
     *
     * @param  array<int, float|int>  $requirementsByMaterialId
     * @return array<int, float>
     */
    public function estimateMaterialUnitCostsForPlanning(int $warehouseId, array $requirementsByMaterialId): array
    {
        if ($requirementsByMaterialId === []) {
            return [];
        }

        $requirements = collect($requirementsByMaterialId)
            ->mapWithKeys(fn ($requiredQuantity, $materialId) => [(int) $materialId => (float) $requiredQuantity])
            ->filter(fn (float $requiredQuantity) => $requiredQuantity > 0);

        if ($requirements->isEmpty()) {
            return [];
        }

        $materialIds = $requirements->keys()->all();
        $batchesByMaterial = InventoryBatch::query()
            ->where('warehouse_id', $warehouseId)
            ->whereIn('raw_material_id', $materialIds)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get(['raw_material_id', 'remaining_quantity', 'unit_price'])
            ->groupBy('raw_material_id');

        $fallbackPrices = RawMaterial::query()
            ->whereIn('id', $materialIds)
            ->pluck('current_price', 'id');

        $estimatedUnitCosts = [];

        foreach ($requirements as $materialId => $requiredQuantity) {
            $batches = $batchesByMaterial->get($materialId, collect());
            $estimatedUnitCost = $this->estimateAverageUnitCostFromBatches($requiredQuantity, $batches);

            if ($estimatedUnitCost <= 0) {
                $estimatedUnitCost = (float) ($fallbackPrices->get($materialId) ?? 0);
            }

            $estimatedUnitCosts[(int) $materialId] = $estimatedUnitCost;
        }

        return $estimatedUnitCosts;
    }

    /**
     * Vista previa de costos para la pantalla de cierre sin consumir inventario.
     *
     * @param  array<int, array{id:int,actual_quantity:float|int}>  $ingredients
     * @param  array<int, array{id:int,actual_units:float|int}>  $packaging
     * @return array{
     *   ingredients: array<int, array{id:int,unit_cost:float,total_cost:float,actual_quantity:float}>,
     *   packaging: array<int, array{id:int,cost_price:float,total_cost:float,equivalent:float,actual_units:float}>,
     *   total_bulk_cost: float,
     *   total_finished_cost: float,
     *   total_equivalent: float
     * }
     */
    public function previewOrderCosts(ProductionOrder $order, array $ingredients, array $packaging): array
    {
        $order->loadMissing(['details', 'packagingPlans.productVariant']);

        $detailsById = $order->details->keyBy('id');
        $ingredientRequirements = [];
        $ingredientRows = [];
        $totalBulkCost = 0.0;

        foreach ($ingredients as $ingredientData) {
            $detailId = (int) ($ingredientData['id'] ?? 0);
            $actualQuantity = max(0.0, (float) ($ingredientData['actual_quantity'] ?? 0));
            $detail = $detailsById->get($detailId);

            if ($detail === null) {
                continue;
            }

            $ingredientRequirements[(int) $detail->raw_material_id] =
                ($ingredientRequirements[(int) $detail->raw_material_id] ?? 0.0) + $actualQuantity;

            $ingredientRows[] = [
                'id' => $detailId,
                'raw_material_id' => (int) $detail->raw_material_id,
                'actual_quantity' => $actualQuantity,
            ];
        }

        $ingredientUnitCosts = $this->estimateMaterialUnitCostsForPlanning(
            warehouseId: (int) $order->warehouse_id,
            requirementsByMaterialId: $ingredientRequirements
        );

        $ingredientResults = [];

        foreach ($ingredientRows as $row) {
            $unitCost = (float) ($ingredientUnitCosts[$row['raw_material_id']] ?? 0.0);
            $totalCost = $row['actual_quantity'] * $unitCost;
            $totalBulkCost += $totalCost;

            $ingredientResults[] = [
                'id' => $row['id'],
                'actual_quantity' => $row['actual_quantity'],
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
            ];
        }

        $distributedBulkCosts = $this->calculateDistributedBulkCosts(
            order: $order,
            packagingData: $packaging,
            totalBulkCost: $totalBulkCost
        );

        $plansById = $order->packagingPlans->keyBy('id');
        $packagingRequirements = [];
        $packagingRows = [];

        foreach ($packaging as $packagingData) {
            $planId = (int) ($packagingData['id'] ?? 0);
            $actualUnits = max(0.0, (float) ($packagingData['actual_units'] ?? 0));
            $plan = $plansById->get($planId);

            if ($plan === null || $actualUnits <= 0) {
                continue;
            }

            $packageRawMaterialId = $plan->productVariant?->package_raw_material_id;
            if ($packageRawMaterialId !== null) {
                $packagingRequirements[(int) $packageRawMaterialId] =
                    ($packagingRequirements[(int) $packageRawMaterialId] ?? 0.0) + $actualUnits;
            }

            $packagingRows[] = [
                'id' => $planId,
                'actual_units' => $actualUnits,
                'product_variant_id' => (int) $plan->product_variant_id,
                'presentation_value' => (float) ($plan->productVariant?->presentation_value ?? 1),
                'package_raw_material_id' => $packageRawMaterialId !== null ? (int) $packageRawMaterialId : null,
            ];
        }

        $packagingUnitCosts = $this->estimateMaterialUnitCostsForPlanning(
            warehouseId: (int) $order->warehouse_id,
            requirementsByMaterialId: $packagingRequirements
        );

        $packagingResults = [];
        $totalFinishedCost = 0.0;
        $totalEquivalent = 0.0;

        foreach ($packagingRows as $row) {
            $bulkCostPerUnit = (float) ($distributedBulkCosts[$row['product_variant_id']] ?? 0.0);
            $packagingUnitCost = $row['package_raw_material_id'] !== null
                ? (float) ($packagingUnitCosts[$row['package_raw_material_id']] ?? 0.0)
                : 0.0;

            $costPrice = $bulkCostPerUnit + $packagingUnitCost;
            $totalCost = $row['actual_units'] * $costPrice;
            $equivalent = $row['actual_units'] * $row['presentation_value'];

            $totalFinishedCost += $totalCost;
            $totalEquivalent += $equivalent;

            $packagingResults[] = [
                'id' => $row['id'],
                'actual_units' => $row['actual_units'],
                'cost_price' => $costPrice,
                'total_cost' => $totalCost,
                'equivalent' => $equivalent,
            ];
        }

        return [
            'ingredients' => $ingredientResults,
            'packaging' => $packagingResults,
            'total_bulk_cost' => $totalBulkCost,
            'total_finished_cost' => $totalFinishedCost,
            'total_equivalent' => $totalEquivalent,
        ];
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

            // 2. Procesar consumo real de materias primas y costo real del granel
            $totalBulkCost = 0.0;
            foreach ($data['ingredients'] as $ingredientData) {
                $detail = ProductionOrderDetail::findOrFail($ingredientData['id']);
                $actualQuantity = (float) $ingredientData['actual_quantity'];

                $consumedCost = $this->consumeRawMaterialFifo($order, $detail, $actualQuantity, $userId);
                $realUnitCost = $actualQuantity > 0 ? ($consumedCost / $actualQuantity) : 0.0;

                $detail->update([
                    'actual_quantity' => $actualQuantity,
                    'unit_cost' => $realUnitCost,
                    'total_cost' => $consumedCost,
                ]);

                $totalBulkCost += $consumedCost;
            }

            // 2.5. Distribuir costo de granel según rendimiento por presentación
            $distributedBulkCosts = $this->calculateDistributedBulkCosts(
                order: $order,
                packagingData: $data['packaging'] ?? [],
                totalBulkCost: $totalBulkCost
            );

            // 3. Procesar Entrada de Producto Terminado (Packaging Plan)
            foreach (($data['packaging'] ?? []) as $packData) {
                $plan = ProductionOrderPackagingPlan::findOrFail($packData['id']);
                $actualUnits = (float) $packData['actual_units'];

                $plan->update(['actual_units' => $actualUnits]);

                if ($actualUnits > 0) {
                    $variant = ProductVariant::query()
                        ->select(['id', 'presentation_value', 'package_raw_material_id'])
                        ->find($plan->product_variant_id);

                    $packagingUnitCost = 0.0;
                    if ($variant?->package_raw_material_id !== null) {
                        $packagingTotalCost = $this->consumeRawMaterialFifoByMaterialId(
                            order: $order,
                            rawMaterialId: (int) $variant->package_raw_material_id,
                            requiredQuantity: $actualUnits,
                            userId: $userId,
                            errorKey: 'packaging',
                            contextLabel: 'envase'
                        );

                        $packagingUnitCost = $actualUnits > 0 ? ($packagingTotalCost / $actualUnits) : 0.0;
                    }

                    $bulkCostForVariant = $distributedBulkCosts[$plan->product_variant_id] ?? 0.0;
                    $costPriceForVariant = $bulkCostForVariant + $packagingUnitCost;

                    // Registrar entrada de producto terminado
                    FinishedInventoryMovement::create([
                        'product_id' => $order->product_id,
                        'product_variant_id' => $plan->product_variant_id,
                        'warehouse_id' => $order->warehouse_id,
                        'production_order_id' => $order->id,
                        'type' => InventoryMovementType::Entry,
                        'quantity' => $actualUnits,
                        // cost_price representa costo unitario del terminado en este movimiento.
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
                }
            }

            $yieldRealQuantity = (float) ($data['actual_yield_quantity'] ?? $order->quantity);
            $yieldTheoreticalQuantity = (float) $order->quantity;
            $yieldVarianceQuantity = $yieldRealQuantity - $yieldTheoreticalQuantity;
            $yieldPercentage = $yieldTheoreticalQuantity > 0
                ? (($yieldRealQuantity / $yieldTheoreticalQuantity) * 100)
                : null;

            $order->update([
                'yield_real_quantity' => $yieldRealQuantity,
                'yield_theoretical_quantity' => $yieldTheoreticalQuantity,
                'yield_variance_quantity' => $yieldVarianceQuantity,
                'yield_percentage' => $yieldPercentage,
            ]);

            // 4. Crear historial de ProductionCost con el costo real del granel (sin envase)
            if ($totalBulkCost > 0) {
                $costPerYieldUnit = $yieldRealQuantity > 0 ? ($totalBulkCost / $yieldRealQuantity) : null;

                ProductionCost::create([
                    'product_id' => $order->product_id,
                    'formula_id' => $order->formula_id,
                    'production_order_id' => $order->id,
                    'cost' => $totalBulkCost,
                    'unit_cost' => $costPerYieldUnit,
                    'calculated_at' => now(),
                ]);
            }

            return $order->refresh();
        });
    }

    private function consumeRawMaterialFifo(ProductionOrder $order, ProductionOrderDetail $detail, float $requiredQuantity, int $userId): float
    {
        return $this->consumeRawMaterialFifoByMaterialId(
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
    ): float {
        if ($requiredQuantity <= 0) {
            return 0.0;
        }

        $remainingToConsume = $requiredQuantity;
        $totalConsumedCost = 0.0;

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
            $unitPrice = (float) $batch->unit_price;

            InventoryMovement::create([
                'raw_material_id' => $rawMaterialId,
                'warehouse_id' => $order->warehouse_id,
                'batch_id' => $batch->id,
                'production_order_id' => $order->id,
                'type' => InventoryMovementType::Exit,
                'quantity' => $consumedQuantity,
                'cost_price' => $unitPrice,
                'movement_date' => now(),
                'notes' => "Consumo FIFO en OP #{$order->order_number}",
                'created_by' => $userId,
            ]);

            $batch->decrement('remaining_quantity', $consumedQuantity);
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
     * Calculate distributed costs for each variant based on bulk cost and presentation_value.
     *
     * @param  array  $packagingData  Array of packaging plan data with 'id' and 'actual_units'
     * @return array Array keyed by product_variant_id with cost_price for each variant
     */
    private function calculateDistributedBulkCosts(ProductionOrder $order, array $packagingData, float $totalBulkCost): array
    {
        if (empty($packagingData) || $totalBulkCost <= 0) {
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

            // Costo por unidad de esta variante sin incluir envase.
            $presentationValue = (float) ($variant->presentation_value ?? 1);
            $distributedCosts[$variant->id] = $costPerUnitBulk * $presentationValue;
        }

        return $distributedCosts;
    }
}
