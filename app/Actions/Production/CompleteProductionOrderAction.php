<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Enums\InventoryMovementType;
use App\Enums\ProductionOrderStatus;
use App\Jobs\RecalculateRawMaterialReferencePrice;
use App\Models\FinishedInventory;
use App\Models\FinishedInventoryMovement;
use App\Models\Product;
use App\Models\ProductionCost;
use App\Models\ProductionOrder;
use App\Models\ProductVariant;
use App\Services\Inventory\FifoStockAllocatorService;
use App\Services\Pricing\ProductionCostCalculatorService;
use App\Services\QualityInspectionCertificateService;
use App\Services\VariantPricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteProductionOrderAction
{
    public function __construct(
        private readonly FifoStockAllocatorService $fifoStockAllocator,
        private readonly ProductionCostCalculatorService $productionCostCalculator,
        private readonly VariantPricingService $variantPricingService,
        private readonly QualityInspectionCertificateService $qualityInspectionCertificateService
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
                ProductionOrderStatus::Pending,
                ProductionOrderStatus::InProgress,
            ];

            if (! in_array($lockedOrder->status, $allowedForCompletion, true)) {
                throw new \DomainException(
                    "No se puede completar una orden en estado '{$lockedOrder->status->label()}'."
                );
            }

            $lockedOrder->update([
                'status' => ProductionOrderStatus::Completed,
                'completion_date' => now(),
                'actual_quantity' => $data['actual_yield_quantity'] ?? $lockedOrder->quantity,
                'viscosity_ku' => $data['viscosity_ku'] ?? null,
                'grinding_hg' => $data['grinding_hg'] ?? null,
                'quality_solids' => $data['quality_solids'] ?? null,
                'agitation_start_time' => $data['agitation_start_time'] ?? null,
                'agitation_end_time' => $data['agitation_end_time'] ?? null,
                'packaging_start_time' => $data['packaging_start_time'] ?? null,
                'packaging_end_time' => $data['packaging_end_time'] ?? null,
                'responsible_name' => $data['responsible_name'] ?? null,
                'spillage_quantity' => $data['spillage_quantity'] ?? 0,
                'notes' => $data['notes'] ?? $lockedOrder->notes,
            ]);

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

                $consumedCost = $this->fifoStockAllocator->consumeProductionOrderDetail(
                    order: $lockedOrder,
                    detail: $detail,
                    requiredQuantity: $actualQuantity,
                    userId: $userId
                );
                $realUnitCost = $actualQuantity > 0 ? ($consumedCost / $actualQuantity) : 0.0;

                $detail->update([
                    'actual_quantity' => $actualQuantity,
                    'unit_cost' => $realUnitCost,
                    'total_cost' => $consumedCost,
                ]);

                $totalBulkCost += $consumedCost;
            }

            foreach ($lockedOrder->lineAdjustments as $adjustment) {
                $consumedRawMaterialIds[] = (int) $adjustment->raw_material_id;

                $totalBulkCost += $this->fifoStockAllocator->consumeRawMaterialForProduction(
                    order: $lockedOrder,
                    rawMaterialId: (int) $adjustment->raw_material_id,
                    requiredQuantity: (float) $adjustment->quantity,
                    userId: $userId,
                    errorKey: 'line_adjustments',
                    contextLabel: 'ajuste de línea'
                );
            }

            $distributedBulkCosts = $this->productionCostCalculator->calculateDistributedBulkCosts(
                order: $lockedOrder,
                packagingData: $data['packaging'] ?? [],
                totalBulkCost: $totalBulkCost
            );

            $productForPricing = Product::query()
                ->select(['id', 'profit_margin', 'price_threshold'])
                ->find($lockedOrder->product_id);
            $autoUpdateVariantPrice = (bool) config('production.auto_update_variant_price', true);
            $productProfitMargin = $productForPricing?->profit_margin !== null
                ? (float) $productForPricing->profit_margin
                : null;
            $productPriceThreshold = (float) ($productForPricing?->price_threshold ?? 0);

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

                if ($actualUnits <= 0) {
                    continue;
                }

                $variant = ProductVariant::query()
                    ->select(['id', 'presentation_value', 'package_raw_material_id', 'current_cost', 'current_price'])
                    ->find($plan->product_variant_id);

                $packagingUnitCost = 0.0;
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

                    $packagingUnitCost = $packagingTotalCost / $actualUnits;
                }

                $bulkCostForVariant = (float) ($distributedBulkCosts[$plan->product_variant_id] ?? 0.0);
                $costPriceForVariant = $bulkCostForVariant + $packagingUnitCost;

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

            collect($consumedRawMaterialIds)
                ->unique()
                ->each(fn (int $id) => RecalculateRawMaterialReferencePrice::dispatch($id)->afterCommit());

            return $lockedOrder->refresh();
        }, attempts: 3);

        $this->qualityInspectionCertificateService->generateForCompletedOrder($completedOrder, $userId);

        return $completedOrder->refresh();
    }
}
