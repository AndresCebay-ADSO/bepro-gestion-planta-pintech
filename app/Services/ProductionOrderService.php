<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Enums\ProductionOrderStatus;
use App\Jobs\RecalculateRawMaterialReferencePrice;
use App\Models\FinishedInventory;
use App\Models\FinishedInventoryMovement;
use App\Models\Formula;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\Product;
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
    public function __construct(
        private readonly VariantPricingService $variantPricingService
    ) {}

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
            $estimatedUnitCost = $this->estimateAverageUnitCostFromBatches($requiredQuantity, $batches);

            if ($estimatedUnitCost <= 0) {
                $estimatedUnitCost = (float) ($rawMaterial?->current_price ?? 0);
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

        // Incluir costo estimado de ajustes de línea en el granel
        $order->loadMissing('lineAdjustments');
        $adjustmentRequirements = [];
        foreach ($order->lineAdjustments as $adjustment) {
            $materialId = (int) $adjustment->raw_material_id;
            $adjustmentRequirements[$materialId] = ($adjustmentRequirements[$materialId] ?? 0.0) + (float) $adjustment->quantity;
        }

        if ($adjustmentRequirements !== []) {
            $adjustmentUnitCosts = $this->estimateMaterialUnitCostsForPlanning(
                warehouseId: (int) $order->warehouse_id,
                requirementsByMaterialId: $adjustmentRequirements
            );

            foreach ($adjustmentRequirements as $materialId => $quantity) {
                $unitCost = (float) ($adjustmentUnitCosts[$materialId] ?? 0.0);
                $totalBulkCost += $quantity * $unitCost;
            }
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

        $requirements = [];
        foreach ($formula->details as $detail) {
            $materialId = (int) $detail->raw_material_id;
            $requirements[$materialId] = ($requirements[$materialId] ?? 0.0) + ((float) $detail->quantity * $quantity);
        }

        $tracksInventoryByMaterialId = RawMaterial::query()
            ->whereIn('id', array_keys($requirements))
            ->pluck('tracks_inventory', 'id');

        foreach ($requirements as $materialId => $required) {
            if (! (bool) ($tracksInventoryByMaterialId->get($materialId) ?? true)) {
                continue;
            }

            $available = (float) InventoryBatch::where('raw_material_id', $materialId)
                ->where('warehouse_id', $warehouseId)
                ->sum('remaining_quantity');

            if ($available < $required) {
                $materialCode = RawMaterial::whereKey($materialId)->value('code') ?? (string) $materialId;
                throw ValidationException::withMessages([
                    'product_id' => "Stock insuficiente de '{$materialCode}' en {$warehouse->name}. Requerido: {$required}, Disponible: {$available}.",
                ]);
            }
        }
    }

    /**
     * Crear una nueva orden (Planificación).
     */
    public function createOrder(array $data): ProductionOrder
    {
        $formula = Formula::findOrFail($data['formula_id']);
        $formula->load('details');

        return DB::transaction(function () use ($data, $formula) {
            // Validar stock antes de crear (advisory check dentro de la transacción)
            $this->validateStockForOrder($formula, (float) $data['quantity'], (int) $data['warehouse_id']);

            $order = ProductionOrder::create([
                'product_id' => $data['product_id'],
                'formula_id' => $data['formula_id'],
                'warehouse_id' => $data['warehouse_id'],
                'quantity' => $data['quantity'],
                'planned_date' => $data['planned_date'],
                'notes' => $data['notes'] ?? null,
                'order_number' => $this->generateOrderNumber(),
                'status' => ProductionOrderStatus::Pending,
                'created_by' => auth()->id(),
            ]);

            // Crear detalles de ingredientes basados en la fórmula (sin reservar lote en planificación)
            foreach ($formula->details as $detail) {
                $plannedQuantity = $detail->quantity * (float) $data['quantity'];
                $estimatedUnitCost = $this->estimateMaterialUnitCostForPlanning(
                    rawMaterialId: (int) $detail->raw_material_id,
                    warehouseId: (int) $data['warehouse_id'],
                    requiredQuantity: (float) $plannedQuantity
                );

                ProductionOrderDetail::create([
                    'production_order_id' => $order->id,
                    'raw_material_id' => $detail->raw_material_id,
                    'batch_id' => null,
                    'step_order' => $detail->step_order,
                    'planned_quantity' => $plannedQuantity,
                    'unit_cost' => $estimatedUnitCost,
                    'total_cost' => $plannedQuantity * $estimatedUnitCost,
                ]);
            }

            // Crear plan de envasado si se proporcionó
            if (! empty($data['packaging'])) {
                foreach ($data['packaging'] as $packData) {
                    ProductionOrderPackagingPlan::create([
                        'production_order_id' => $order->id,
                        'product_variant_id' => $packData['product_variant_id'],
                        'planned_units' => $packData['planned_units'],
                    ]);
                }
            }

            return $order;
        });
    }

    /**
     * Genera un número de orden secuencial: OP-YYYY-XXXX (reinicia cada año).
     */
    private function generateOrderNumber(): string
    {
        $prefix = 'OP-'.now()->format('Y').'-';

        if (DB::connection()->getDriverName() === 'pgsql') {
            // PostgreSQL advisory lock to prevent race conditions when 0 rows exist
            $lockKey = crc32($prefix);
            DB::statement('SELECT pg_advisory_xact_lock(?)', [$lockKey]);
        }

        $lastOrder = ProductionOrder::where('order_number', 'like', $prefix.'%')
            ->orderByDesc('order_number')
            ->value('order_number');

        $nextSequence = 1;
        if ($lastOrder !== null) {
            $lastSequence = (int) substr($lastOrder, strlen($prefix));
            $nextSequence = $lastSequence + 1;
        }

        return $prefix.str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Cerrar una orden de producción, procesando el consumo real y la entrada de producto terminado.
     */
    public function completeOrder(ProductionOrder $order, array $data): ProductionOrder
    {
        return DB::transaction(function () use ($order, $data) {
            $lockedOrder = ProductionOrder::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            $allowedForCompletion = [
                ProductionOrderStatus::Pending,
                ProductionOrderStatus::InProgress,
            ];

            if (! in_array($lockedOrder->status, $allowedForCompletion, true)) {
                throw new \DomainException(
                    "No se puede completar una orden en estado '{$lockedOrder->status->label()}'."
                );
            }

            $userId = auth()->id() ?? throw new \RuntimeException('No authenticated user');

            // 1. Actualizar metadatos operacionales de la orden
            $lockedOrder->update([
                'status' => ProductionOrderStatus::Completed,
                'completion_date' => now(),
                'actual_quantity' => $data['actual_yield_quantity'] ?? $lockedOrder->quantity,
                'viscosity_ku' => $data['viscosity_ku'] ?? null,
                'grinding_hg' => $data['grinding_hg'] ?? null,
                'agitation_start_time' => $data['agitation_start_time'] ?? null,
                'agitation_end_time' => $data['agitation_end_time'] ?? null,
                'packaging_start_time' => $data['packaging_start_time'] ?? null,
                'packaging_end_time' => $data['packaging_end_time'] ?? null,
                'responsible_name' => $data['responsible_name'] ?? null,
                'spillage_quantity' => $data['spillage_quantity'] ?? 0,
                'notes' => $data['notes'] ?? $lockedOrder->notes,
            ]);

            // 2. Procesar consumo real de materias primas y costo real del granel
            $lockedOrder->loadMissing(['details', 'packagingPlans.productVariant', 'lineAdjustments']);
            $detailsById = $lockedOrder->details->keyBy('id');
            $totalBulkCost = 0.0;
            $consumedRawMaterialIds = [];
            foreach ($data['ingredients'] as $ingredientData) {
                $detail = $detailsById->get((int) $ingredientData['id']);
                if ($detail === null) {
                    throw ValidationException::withMessages([
                        'ingredients' => __('Uno de los ingredientes no pertenece a la orden de producción seleccionada.'),
                    ]);
                }

                $actualQuantity = (float) $ingredientData['actual_quantity'];
                $consumedRawMaterialIds[] = (int) $detail->raw_material_id;

                $consumedCost = $this->consumeRawMaterialFifo($lockedOrder, $detail, $actualQuantity, $userId);
                $realUnitCost = $actualQuantity > 0 ? ($consumedCost / $actualQuantity) : 0.0;

                $detail->update([
                    'actual_quantity' => $actualQuantity,
                    'unit_cost' => $realUnitCost,
                    'total_cost' => $consumedCost,
                ]);

                $totalBulkCost += $consumedCost;
            }

            // 2.1. Procesar consumo de ajustes de línea (MPs fuera de fórmula)
            foreach ($lockedOrder->lineAdjustments as $adjustment) {
                $consumedRawMaterialIds[] = (int) $adjustment->raw_material_id;
                $adjustmentCost = $this->consumeRawMaterialFifoByMaterialId(
                    order: $lockedOrder,
                    rawMaterialId: (int) $adjustment->raw_material_id,
                    requiredQuantity: (float) $adjustment->quantity,
                    userId: $userId,
                    errorKey: 'line_adjustments',
                    contextLabel: 'ajuste de línea'
                );

                $totalBulkCost += $adjustmentCost;
            }

            // 2.5. Distribuir costo de granel según rendimiento por presentación
            $distributedBulkCosts = $this->calculateDistributedBulkCosts(
                order: $lockedOrder,
                packagingData: $data['packaging'] ?? [],
                totalBulkCost: $totalBulkCost
            );
            $productForPricing = Product::query()
                ->select(['id', 'profit_margin', 'price_threshold'])
                ->find($lockedOrder->product_id);
            $autoUpdateVariantPrice = (bool) config('production.auto_update_variant_price', true);
            $productProfitMargin = $productForPricing?->profit_margin !== null ? (float) $productForPricing->profit_margin : null;
            $productPriceThreshold = (float) ($productForPricing?->price_threshold ?? 0);

            // 3. Procesar Entrada de Producto Terminado (Packaging Plan)
            $packagingPlansById = $lockedOrder->packagingPlans->keyBy('id');
            foreach (($data['packaging'] ?? []) as $packData) {
                $plan = $packagingPlansById->get((int) $packData['id']);
                if ($plan === null) {
                    throw ValidationException::withMessages([
                        'packaging' => __('Uno de los planes de envasado no pertenece a la orden de producción seleccionada.'),
                    ]);
                }
                $actualUnits = (float) $packData['actual_units'];

                $plan->update(['actual_units' => $actualUnits]);

                if ($actualUnits > 0) {
                    $variant = ProductVariant::query()
                        ->select(['id', 'presentation_value', 'package_raw_material_id', 'current_cost', 'current_price'])
                        ->find($plan->product_variant_id);

                    $packagingUnitCost = 0.0;
                    if ($variant?->package_raw_material_id !== null) {
                        $consumedRawMaterialIds[] = (int) $variant->package_raw_material_id;
                        $packagingTotalCost = $this->consumeRawMaterialFifoByMaterialId(
                            order: $lockedOrder,
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
                        'product_id' => $lockedOrder->product_id,
                        'product_variant_id' => $plan->product_variant_id,
                        'warehouse_id' => $lockedOrder->warehouse_id,
                        'production_order_id' => $lockedOrder->id,
                        'type' => InventoryMovementType::Entry,
                        'quantity' => $actualUnits,
                        // cost_price representa costo unitario del terminado en este movimiento.
                        'cost_price' => $costPriceForVariant,
                        'movement_date' => now(),
                        'notes' => "Finalización OP #{$lockedOrder->order_number}",
                        'created_by' => $userId,
                    ]);

                    if ($variant !== null) {
                        $presentationValue = (float) ($variant->presentation_value ?? 1);
                        $bulkCost = $presentationValue > 0 ? ($bulkCostForVariant / $presentationValue) : 0.0;

                        $this->variantPricingService->updateVariantCostAndPrice(
                            variant: $variant,
                            bulkCost: $bulkCost,
                            profitMargin: $productProfitMargin,
                            priceThreshold: $productPriceThreshold,
                            packageUnitCost: $packagingUnitCost,
                            autoUpdatePrice: $autoUpdateVariantPrice,
                            forceRefresh: false
                        );
                    }

                    // Actualizar o crear registro en FinishedInventory (por variante)
                    $inventory = FinishedInventory::query()
                        ->lockForUpdate()
                        ->firstOrNew([
                            'product_id' => $lockedOrder->product_id,
                            'product_variant_id' => $plan->product_variant_id,
                            'warehouse_id' => $lockedOrder->warehouse_id,
                        ]);

                    $inventory->quantity = ($inventory->quantity ?? 0) + $actualUnits;
                    $inventory->save();
                }
            }

            $yieldRealQuantity = (float) ($data['actual_yield_quantity'] ?? $lockedOrder->quantity);
            $yieldTheoreticalQuantity = (float) $lockedOrder->quantity;
            $yieldVarianceQuantity = $yieldRealQuantity - $yieldTheoreticalQuantity;
            $yieldPercentage = $yieldTheoreticalQuantity > 0
                ? (($yieldRealQuantity / $yieldTheoreticalQuantity) * 100)
                : null;

            $lockedOrder->update([
                'yield_real_quantity' => $yieldRealQuantity,
                'yield_theoretical_quantity' => $yieldTheoreticalQuantity,
                'yield_variance_quantity' => $yieldVarianceQuantity,
                'yield_percentage' => $yieldPercentage,
            ]);

            // 4. Crear / actualizar historial de ProductionCost con el costo real del granel (sin envase)
            if ($totalBulkCost > 0) {
                $costPerYieldUnit = $yieldRealQuantity > 0 ? ($totalBulkCost / $yieldRealQuantity) : null;

                ProductionCost::updateOrCreate(
                    ['production_order_id' => $lockedOrder->id],
                    [
                        'product_id' => $lockedOrder->product_id,
                        'formula_id' => $lockedOrder->formula_id,
                        'cost' => $totalBulkCost,
                        'unit_cost' => $costPerYieldUnit,
                        'calculated_at' => now(),
                    ]
                );
            }

            // 5. Despachar recálculo de precio de referencia para cada MP consumida
            collect($consumedRawMaterialIds)
                ->unique()
                ->each(fn (int $id) => RecalculateRawMaterialReferencePrice::dispatch($id)->afterCommit());

            return $lockedOrder->refresh();
        });
    }

    public function cancelOrder(ProductionOrder $order, ?string $reason = null): ProductionOrder
    {
        return DB::transaction(function () use ($order, $reason) {
            $lockedOrder = ProductionOrder::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            $allowedForCancellation = [
                ProductionOrderStatus::Pending,
                ProductionOrderStatus::InProgress,
            ];

            if (! in_array($lockedOrder->status, $allowedForCancellation, true)) {
                throw new \DomainException(
                    "No se puede cancelar una orden en estado '{$lockedOrder->status->label()}'."
                );
            }

            $notes = $lockedOrder->notes;
            if ($reason !== null && $reason !== '') {
                $notes = trim(implode("\n\n", array_filter([
                    $lockedOrder->notes,
                    "Cancelación: {$reason}",
                ])));
            }

            $lockedOrder->update([
                'status' => ProductionOrderStatus::Cancelled,
                'notes' => $notes,
            ]);

            return $lockedOrder->refresh();
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

        /** @var RawMaterial|null $rawMaterial */
        $rawMaterial = RawMaterial::query()
            ->select(['id', 'code', 'current_price', 'tracks_inventory'])
            ->find($rawMaterialId);
        $materialCode = $rawMaterial?->code ?? (string) $rawMaterialId;

        if ($rawMaterial !== null && ! $rawMaterial->tracks_inventory) {
            $unitPrice = (float) ($rawMaterial->current_price ?? 0);

            InventoryMovement::create([
                'raw_material_id' => $rawMaterialId,
                'warehouse_id' => $order->warehouse_id,
                'batch_id' => null,
                'production_order_id' => $order->id,
                'type' => InventoryMovementType::Exit,
                'quantity' => $requiredQuantity,
                'cost_price' => $unitPrice,
                'movement_date' => now(),
                'notes' => "Consumo sin control de inventario en OP #{$order->order_number}",
                'created_by' => $userId,
            ]);

            return $requiredQuantity * $unitPrice;
        }

        $batches = InventoryBatch::query()
            ->where('raw_material_id', $rawMaterialId)
            ->where('warehouse_id', $order->warehouse_id)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('entry_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

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

        // Usar la relación ya cargada (loadMissing en completeOrder/previewOrderCosts)
        $order->loadMissing('packagingPlans.productVariant');
        $plans = $order->packagingPlans;

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
