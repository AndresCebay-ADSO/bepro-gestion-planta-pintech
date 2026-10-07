<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Enums\FinishedInventoryMovementReason;
use App\Enums\ProductionOrderStatus;
use App\Enums\RemnantStatus;
use App\Jobs\GenerateQualityInspectionCertificateJob;
use App\Jobs\RecalculateRawMaterialReferencePrice;
use App\Models\FinishedProductBatch;
use App\Models\Product;
use App\Models\ProductionCost;
use App\Models\ProductionOrder;
use App\Models\ProductionRemnant;
use App\Services\AlertService;
use App\Services\DecimalCalculator;
use App\Services\FinishedInventory\FinishedInventoryMovementService;
use App\Services\Inventory\FifoStockAllocatorService;
use App\Services\Pricing\ProductionCostCalculatorService;
use App\Services\TimezoneService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteProductionOrderAction
{
    public function __construct(
        private readonly FifoStockAllocatorService $fifoStockAllocator,
        private readonly ProductionCostCalculatorService $productionCostCalculator,
        private readonly DecimalCalculator $calculator,
        private readonly AlertService $alertService,
        private readonly SaveProductionOrderOperationalDataAction $saveOperationalData,
        private readonly FinishedInventoryMovementService $finishedInventoryMovementService,
        private readonly TimezoneService $timezone,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(ProductionOrder $order, array $data, int $userId): ProductionOrder
    {
        $completedOrder = DB::transaction(function () use ($order, $data, $userId): ProductionOrder {
            $lockedOrder = ProductionOrder::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            $allowedForCompletion = [
                ProductionOrderStatus::InProgress,
                ProductionOrderStatus::PendingReview,
            ];

            if (! in_array($lockedOrder->status, $allowedForCompletion, true)) {
                throw new \DomainException(
                    "No se puede completar una orden en estado '{$lockedOrder->status->label()}'."
                );
            }

            $wasPendingReview = $lockedOrder->status === ProductionOrderStatus::PendingReview;

            $this->saveOperationalData->execute($lockedOrder, $data);

            // Fecha de planta, no UTC: completada a las 8 p. m. en Bogotá ya es el día siguiente en UTC, y el dashboard
            // («completadas hoy»), el detalle y el QR la mostrarían un día después. Es la fecha de todo lo que escribe
            // completar la orden (la orden, sus consumos, sus lotes de PT y sus entradas), calculada una sola vez para
            // que una orden completada cerca de la medianoche no reparta sus registros en dos días (B54, B55).
            $completionDate = $this->timezone->todayInPlant();

            $updateData = [
                'status' => ProductionOrderStatus::Completed,
                'completion_date' => $completionDate,
                'quality_responsible_user_id' => $data['quality_responsible_user_id'] ?? null,
            ];

            if ($wasPendingReview) {
                $updateData['reviewed_by'] = $userId;
                $updateData['reviewed_at'] = now();
            }

            $lockedOrder->update($updateData);

            $lockedOrder->loadMissing(['details', 'packagingPlans.productVariant', 'lineAdjustments', 'remnantConsumptions']);
            $detailsById = $lockedOrder->details->keyBy('id');
            $totalBulkCost = '0';
            $consumedRawMaterialIds = [];

            foreach ($data['ingredients'] as $ingredientData) {
                $detail = $detailsById->get((int) $ingredientData['id']);
                if ($detail === null) {
                    throw ValidationException::withMessages([
                        'ingredients' => __('Uno de los ingredientes no pertenece a la orden de producción seleccionada.'),
                    ]);
                }

                $actualQuantity = (string) $ingredientData['actual_quantity'];
                $consumedRawMaterialIds[] = (int) $detail->raw_material_id;

                $consumedCost = $this->fifoStockAllocator->consumeProductionOrderDetail(
                    order: $lockedOrder,
                    detail: $detail,
                    requiredQuantity: $actualQuantity,
                    userId: $userId,
                    movementDate: $completionDate,
                );
                $realUnitCost = $this->calculator->cmp($actualQuantity, '0', 4) > 0
                    ? $this->calculator->div($consumedCost, $actualQuantity, 4)
                    : '0';

                $detail->update([
                    'actual_quantity' => $actualQuantity,
                    'unit_cost' => $realUnitCost,
                    'total_cost' => $consumedCost,
                ]);

                $totalBulkCost = $this->calculator->add($totalBulkCost, $consumedCost, 4);
            }

            foreach ($lockedOrder->lineAdjustments as $adjustment) {
                $consumedRawMaterialIds[] = (int) $adjustment->raw_material_id;

                $totalBulkCost = $this->calculator->add($totalBulkCost, (string) $this->fifoStockAllocator->consumeRawMaterialForProduction(
                    order: $lockedOrder,
                    rawMaterialId: (int) $adjustment->raw_material_id,
                    requiredQuantity: (string) $adjustment->quantity,
                    userId: $userId,
                    errorKey: 'line_adjustments',
                    contextLabel: 'ajuste de línea',
                    movementDate: $completionDate,
                ), 4);
            }

            foreach ($lockedOrder->remnantConsumptions as $consumption) {
                if ($consumption->consumed_cost !== null) {
                    $totalBulkCost = $this->calculator->add(
                        $totalBulkCost,
                        (string) $consumption->consumed_cost,
                        4
                    );
                }
            }

            $remnantGallons = (string) ($data['remnant_quantity_gallons'] ?? '0');

            $costDistribution = $this->productionCostCalculator->calculateDistributedBulkCosts(
                order: $lockedOrder,
                packagingData: $data['packaging'] ?? [],
                totalBulkCost: $totalBulkCost,
                remnantGallons: $this->calculator->cmp($remnantGallons, '0', 4) > 0 ? $remnantGallons : null
            );
            $distributedBulkCosts = $costDistribution['distributedCosts'];
            $bulkCostPerUnit = $costDistribution['bulkCostPerUnit'];

            $productForPricing = Product::query()
                ->select(['id', 'cif_percentage'])
                ->find($lockedOrder->product_id);
            $productCifPercentage = $productForPricing?->cif_percentage !== null
                ? (string) $productForPricing->cif_percentage
                : null;

            $packagingPlansById = $lockedOrder->packagingPlans->keyBy('id');

            foreach (($data['packaging'] ?? []) as $packData) {
                $plan = $packagingPlansById->get((int) $packData['id']);
                if ($plan === null) {
                    throw ValidationException::withMessages([
                        'packaging' => __('Uno de los planes de envasado no pertenece a la orden de producción seleccionada.'),
                    ]);
                }

                $actualUnits = (string) $packData['actual_units'];

                if ($this->calculator->cmp($actualUnits, '0', 4) <= 0) {
                    // Sin unidades no se consume nada, y el plan cerrado lo dice: sin envases nuevos ni etiquetas usadas
                    // (un avance guardado antes con unidades pudo dejarlos). El envase se guarda igual: el documento
                    // cerrado no debe cambiar si después se cambia el de la presentación.
                    $plan->update([
                        'actual_units' => $actualUnits,
                        'package_raw_material_id' => $plan->packagingConsumption($actualUnits)['package_id'],
                        'new_containers_used' => null,
                        'labels_used' => null,
                    ]);

                    continue;
                }

                // Empaque (3.7). Los datos del plan ya vienen guardados por saveOperationalData. Se guarda lo que se usó,
                // incluido el envase: el documento no cambia si después se cambia el de la presentación.
                $consumption = $plan->packagingConsumption($actualUnits);
                $newContainers = $consumption['new_containers'];
                $labelsUsed = $consumption['labels_used'];

                $plan->update([
                    'actual_units' => $actualUnits,
                    'package_raw_material_id' => $consumption['package_id'],
                    'new_containers_used' => $newContainers,
                    'labels_used' => $labelsUsed,
                ]);

                $packagingTotalCost = $this->calculator->add(
                    $this->consumePackagingMaterial($lockedOrder, $consumption['package_id'], $newContainers, 'envase', $userId, $completionDate, $consumedRawMaterialIds),
                    $this->consumePackagingMaterial($lockedOrder, $consumption['label_id'], $labelsUsed, 'etiqueta', $userId, $completionDate, $consumedRawMaterialIds),
                    4
                );

                // Envases nuevos y etiquetas, repartidos entre las unidades envasadas.
                $packagingUnitCost = $this->calculator->div($packagingTotalCost, $actualUnits, 4);

                $bulkCostForVariant = (string) ($distributedBulkCosts[$plan->product_variant_id] ?? '0');
                $costPriceForVariant = $this->calculator->add($bulkCostForVariant, $packagingUnitCost, 4);

                $batch = FinishedProductBatch::create([
                    'product_id' => $lockedOrder->product_id,
                    'product_variant_id' => $plan->product_variant_id,
                    'production_order_id' => $lockedOrder->id,
                    'initial_quantity' => $actualUnits,
                    'entry_date' => $completionDate,
                ]);

                $this->finishedInventoryMovementService->registerEntry(
                    batchId: (int) $batch->id,
                    warehouseId: (int) $lockedOrder->warehouse_id,
                    quantity: (string) $actualUnits,
                    reason: FinishedInventoryMovementReason::Production,
                    userId: $userId,
                    productionOrderId: (int) $lockedOrder->id,
                    costPrice: $costPriceForVariant,
                    notes: "Finalización OP #{$lockedOrder->order_number}",
                    movementDate: $completionDate,
                );
            }

            $yieldRealQuantity = (string) ($data['actual_yield_quantity'] ?? $lockedOrder->quantity);
            $yieldTheoreticalQuantity = (string) $lockedOrder->quantity;
            $yieldVarianceQuantity = $this->calculator->sub($yieldRealQuantity, $yieldTheoreticalQuantity, 4);
            $yieldPercentage = $this->calculator->cmp($yieldTheoreticalQuantity, '0', 4) > 0
                ? $this->calculator->mul($this->calculator->div($yieldRealQuantity, $yieldTheoreticalQuantity, 4), '100', 4)
                : null;

            $lockedOrder->update([
                'yield_real_quantity' => $yieldRealQuantity,
                'yield_theoretical_quantity' => $yieldTheoreticalQuantity,
                'yield_variance_quantity' => $yieldVarianceQuantity,
                'yield_percentage' => $yieldPercentage,
            ]);

            // TODO: Revisar si estas comparaciones con scale 4 deberían usar
            // una escala mayor para evitar tratar valores < 0.0001 como cero.
            if ($this->calculator->isPositive($totalBulkCost)) {
                $costPerYieldUnit = $this->calculator->cmp($yieldRealQuantity, '0', 4) > 0
                    ? $this->calculator->div($totalBulkCost, $yieldRealQuantity, 4)
                    : null;

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

            $this->registerRemnantIfApplicable(
                order: $lockedOrder,
                data: $data,
                bulkCostPerUnit: $bulkCostPerUnit,
                cifPercentage: $productCifPercentage,
                userId: $userId
            );

            $uniqueConsumedRawMaterialIds = collect($consumedRawMaterialIds)->unique()->values();

            $uniqueConsumedRawMaterialIds
                ->each(fn (int $id) => RecalculateRawMaterialReferencePrice::dispatch($id)->afterCommit());

            $uniqueConsumedRawMaterialIds
                ->each(fn (int $id) => $this->alertService->evaluateLowStock($id));

            return $lockedOrder->refresh();
        }, attempts: 3);

        GenerateQualityInspectionCertificateJob::dispatch($completedOrder, $userId)->afterCommit();

        return $completedOrder;
    }

    /**
     * Consume un material de empaque del plan (envase nuevo, etiqueta) con la regla de cualquier materia prima: por FIFO
     * si controla inventario, a su precio de referencia si no. Devuelve su costo total ('0' si no hay nada que consumir).
     * Un material nuevo del empaque se consume por aquí, no copiando el bloque.
     *
     * @param  list<int>  $consumedRawMaterialIds  se le agrega la materia prima, para recalcular su precio y su stock
     */
    private function consumePackagingMaterial(
        ProductionOrder $order,
        ?int $rawMaterialId,
        ?string $quantity,
        string $contextLabel,
        int $userId,
        string $movementDate,
        array &$consumedRawMaterialIds,
    ): string {
        if ($rawMaterialId === null || $quantity === null || ! $this->calculator->isPositive($quantity)) {
            return '0';
        }

        $consumedRawMaterialIds[] = $rawMaterialId;

        return (string) $this->fifoStockAllocator->consumeRawMaterialForProduction(
            order: $order,
            rawMaterialId: $rawMaterialId,
            requiredQuantity: $quantity,
            userId: $userId,
            errorKey: 'packaging',
            contextLabel: $contextLabel,
            movementDate: $movementDate,
        );
    }

    /**
     * Si se reportaron galones sobrantes, crear un registro de saldo de PT.
     *
     * @param  array<string, mixed>  $data
     */
    private function registerRemnantIfApplicable(
        ProductionOrder $order,
        array $data,
        ?string $bulkCostPerUnit,
        ?string $cifPercentage,
        int $userId
    ): void {
        $remnantGallons = (string) ($data['remnant_quantity_gallons'] ?? '0');

        if ($this->calculator->cmp($remnantGallons, '0', 4) <= 0) {
            return;
        }

        $density = (string) $order->density_kg_per_gallon;
        $remnantKg = $this->calculator->mul($remnantGallons, $density, 4);

        $costPerGallon = $bulkCostPerUnit ?? '0';

        if ($cifPercentage !== null && $this->calculator->cmp($cifPercentage, '0', 4) > 0) {
            $costPerGallon = $this->productionCostCalculator->applyCifToCost($costPerGallon, $cifPercentage);
        }

        ProductionRemnant::create([
            'source_order_id' => $order->id,
            'product_id' => $order->product_id,
            'warehouse_id' => $order->warehouse_id,
            'original_quantity_gallons' => $remnantGallons,
            'original_quantity_kg' => $remnantKg,
            'available_quantity_gallons' => $remnantGallons,
            'available_quantity_kg' => $remnantKg,
            'density_kg_per_gallon' => $density,
            'cost_per_gallon' => $costPerGallon,
            'status' => RemnantStatus::Available,
            'notes' => $data['remnant_notes'] ?? null,
            'created_by' => $userId,
        ]);
    }
}
