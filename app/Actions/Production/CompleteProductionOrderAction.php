<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Enums\InventoryMovementType;
use App\Enums\ProductionOrderStatus;
use App\Enums\RemnantStatus;
use App\Jobs\GenerateQualityInspectionCertificateJob;
use App\Jobs\RecalculateRawMaterialReferencePrice;
use App\Models\FinishedInventory;
use App\Models\FinishedInventoryMovement;
use App\Models\Product;
use App\Models\ProductionCost;
use App\Models\ProductionOrder;
use App\Models\ProductionRemnant;
use App\Models\ProductVariant;
use App\Services\AlertService;
use App\Services\DecimalCalculator;
use App\Services\Inventory\FifoStockAllocatorService;
use App\Services\Pricing\ProductionCostCalculatorService;
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

            $updateData = [
                'status' => ProductionOrderStatus::Completed,
                'completion_date' => now(),
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
                    userId: $userId
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
                    contextLabel: 'ajuste de línea'
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
                $plan->update(['actual_units' => $actualUnits]);

                if ($this->calculator->cmp($actualUnits, '0', 4) <= 0) {
                    continue;
                }

                $variant = ProductVariant::query()
                    ->select(['id', 'package_raw_material_id'])
                    ->find($plan->product_variant_id);

                $packagingUnitCost = '0';
                if ($variant?->package_raw_material_id !== null) {
                    $consumedRawMaterialIds[] = (int) $variant->package_raw_material_id;

                    $packagingTotalCost = $this->fifoStockAllocator->consumeRawMaterialForProduction(
                        order: $lockedOrder,
                        rawMaterialId: (int) $variant->package_raw_material_id,
                        requiredQuantity: $actualUnits,
                        userId: $userId,
                        errorKey: 'packaging',
                        contextLabel: 'envase'
                    );

                    $packagingUnitCost = $this->calculator->div((string) $packagingTotalCost, (string) $actualUnits, 4);
                }

                $bulkCostForVariant = (string) ($distributedBulkCosts[$plan->product_variant_id] ?? '0');
                $costPriceForVariant = $this->calculator->add($bulkCostForVariant, $packagingUnitCost, 4);

                FinishedInventoryMovement::create([
                    'product_id' => $lockedOrder->product_id,
                    'product_variant_id' => $plan->product_variant_id,
                    'warehouse_id' => $lockedOrder->warehouse_id,
                    'production_order_id' => $lockedOrder->id,
                    'type' => InventoryMovementType::Entry,
                    'quantity' => $actualUnits,
                    'cost_price' => $costPriceForVariant,
                    'movement_date' => now(),
                    'notes' => "Finalización OP #{$lockedOrder->order_number}",
                    'created_by' => $userId,
                ]);

                $inventory = FinishedInventory::query()
                    ->lockForUpdate()
                    ->firstOrNew([
                        'product_id' => $lockedOrder->product_id,
                        'product_variant_id' => $plan->product_variant_id,
                        'warehouse_id' => $lockedOrder->warehouse_id,
                    ]);

                $inventory->quantity = $this->calculator->add((string) ($inventory->quantity ?? '0'), $actualUnits, 4);
                $inventory->save();
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
