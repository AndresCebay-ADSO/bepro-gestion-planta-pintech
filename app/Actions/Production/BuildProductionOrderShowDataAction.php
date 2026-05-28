<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\ProductionOrderLineAdjustment;
use App\Models\ProductionOrderPackagingPlan;
use App\Services\DecimalCalculator;
use App\Services\Inventory\FifoStockAllocatorService;

class BuildProductionOrderShowDataAction
{
    public function __construct(
        private readonly FifoStockAllocatorService $fifoStockAllocator,
        private readonly DecimalCalculator $calculator
    ) {}

    /**
     * Carga relaciones y transforma la orden en un array para la pantalla Inertia.
     *
     * @return array<string, mixed>
     */
    public function execute(ProductionOrder $productionOrder): array
    {
        $productionOrder->load([
            'product',
            'qrCode',
            'formula.details.rawMaterial',
            'details.rawMaterial',
            'packagingPlans.productVariant.packageRawMaterial',
            'finishedInventoryMovements',
            'warehouse',
            'lineAdjustments.rawMaterial',
        ]);

        $finishedCostByVariant = $productionOrder->finishedInventoryMovements
            ->keyBy('product_variant_id');

        $packageRawMaterialRequirements = $productionOrder->packagingPlans
            ->map(fn (ProductionOrderPackagingPlan $plan) => $plan->productVariant?->package_raw_material_id)
            ->filter()
            ->unique()
            ->mapWithKeys(fn ($rawMaterialId) => [(int) $rawMaterialId => 1.0])
            ->all();

        $packageUnitCostEstimates = $this->fifoStockAllocator->estimateMaterialUnitCostsForPlanning(
            warehouseId: (int) $productionOrder->warehouse_id,
            requirementsByMaterialId: $packageRawMaterialRequirements
        );

        $totalFinishedCostStr = $productionOrder->finishedInventoryMovements
            ->reduce(function ($carry, $movement) {
                $qty = (string) $movement->quantity;
                $costPrice = (string) ($movement->cost_price ?? '0');
                $itemTotal = $this->calculator->mul($qty, $costPrice, 4);

                return $this->calculator->add((string) $carry, $itemTotal, 4);
            }, '0');
        $totalBulkCostStr = $productionOrder->details
            ->reduce(function ($carry, ProductionOrderDetail $detail) {
                $detailCost = (string) ($detail->total_cost ?? '0');

                return $this->calculator->add((string) $carry, $detailCost, 4);
            }, '0');

        $qrCode = $productionOrder->qrCode;
        $qrLandingUrl = ($qrCode && $qrCode->is_active)
            ? route('qr.public.show', ['token' => $qrCode->token], false)
            : null;
        $qrImageUrl = ($qrCode && $qrCode->is_active)
            ? route('qr.public.image', ['token' => $qrCode->token], false)
            : null;

        return [
            'id' => $productionOrder->id,
            'order_number' => $productionOrder->order_number,
            'status' => $productionOrder->status->value,
            'quantity' => (float) $productionOrder->quantity,
            'actual_quantity' => $productionOrder->actual_quantity !== null ? (float) $productionOrder->actual_quantity : null,
            'yield_real_quantity' => $productionOrder->yield_real_quantity !== null ? (float) $productionOrder->yield_real_quantity : null,
            'yield_theoretical_quantity' => $productionOrder->yield_theoretical_quantity !== null ? (float) $productionOrder->yield_theoretical_quantity : null,
            'yield_variance_quantity' => $productionOrder->yield_variance_quantity !== null ? (float) $productionOrder->yield_variance_quantity : null,
            'yield_percentage' => $productionOrder->yield_percentage !== null ? (float) $productionOrder->yield_percentage : null,
            'planned_date' => optional($productionOrder->planned_date)->toDateString(),
            'completion_date' => optional($productionOrder->completion_date)->toISOString(),
            'viscosity_ku' => $productionOrder->viscosity_ku !== null ? (float) $productionOrder->viscosity_ku : null,
            'grinding_hg' => $productionOrder->grinding_hg !== null ? (float) $productionOrder->grinding_hg : null,
            'quality_solids' => $productionOrder->quality_solids !== null ? (float) $productionOrder->quality_solids : null,
            'agitation_start_time' => optional($productionOrder->agitation_start_time)->format('Y-m-d\TH:i'),
            'agitation_end_time' => optional($productionOrder->agitation_end_time)->format('Y-m-d\TH:i'),
            'packaging_start_time' => optional($productionOrder->packaging_start_time)->format('Y-m-d\TH:i'),
            'packaging_end_time' => optional($productionOrder->packaging_end_time)->format('Y-m-d\TH:i'),
            'responsible_name' => $productionOrder->responsible_name,
            'spillage_quantity' => (float) $productionOrder->spillage_quantity,
            'notes' => $productionOrder->notes,
            'qr_landing_url' => $qrLandingUrl,
            'qr_image_url' => $qrImageUrl,
            'product' => $productionOrder->product ? [
                'id' => $productionOrder->product->id,
                'name' => $productionOrder->product->name,
                'code' => $productionOrder->product->code,
                'profit_margin' => $productionOrder->product->profit_margin !== null ? (float) $productionOrder->product->profit_margin : null,
                'quality_solids_lower' => $productionOrder->product->quality_solids_lower !== null
                    ? (float) $productionOrder->product->quality_solids_lower
                    : null,
                'quality_solids_upper' => $productionOrder->product->quality_solids_upper !== null
                    ? (float) $productionOrder->product->quality_solids_upper
                    : null,
            ] : null,
            'formula' => $productionOrder->formula ? [
                'id' => $productionOrder->formula->id,
                'version' => $productionOrder->formula->version,
            ] : null,
            'warehouse' => $productionOrder->warehouse ? [
                'id' => $productionOrder->warehouse->id,
                'name' => $productionOrder->warehouse->name,
            ] : null,
            'total_bulk_cost' => $totalBulkCostStr,
            'total_finished_cost' => $totalFinishedCostStr,
            'details' => $productionOrder->details->map(fn (ProductionOrderDetail $detail) => [
                'id' => $detail->id,
                'raw_material_id' => (int) $detail->raw_material_id,
                'step_order' => (int) $detail->step_order,
                'planned_quantity' => (float) $detail->planned_quantity,
                'actual_quantity' => $detail->actual_quantity !== null ? (float) $detail->actual_quantity : null,
                'unit_cost' => (string) $detail->unit_cost,
                'total_cost' => (string) $detail->total_cost,
                'raw_material' => $detail->rawMaterial ? [
                    'id' => $detail->rawMaterial->id,
                    'code' => $detail->rawMaterial->code,
                ] : null,
            ])->values(),
            'packaging_plans' => $productionOrder->packagingPlans->map(function (ProductionOrderPackagingPlan $plan) use ($finishedCostByVariant, $packageUnitCostEstimates) {
                $presentationValue = (float) ($plan->productVariant?->presentation_value ?? 1);
                $costMovement = $finishedCostByVariant->get($plan->product_variant_id);
                $packageRawMaterialId = $plan->productVariant?->package_raw_material_id;
                $packageUnitCostEstimate = $packageRawMaterialId !== null
                    ? (string) ($packageUnitCostEstimates[(int) $packageRawMaterialId] ?? '0')
                    : null;

                return [
                    'id' => $plan->id,
                    'planned_units' => (float) $plan->planned_units,
                    'actual_units' => $plan->actual_units !== null ? (float) $plan->actual_units : null,
                    'cost_price' => $costMovement?->cost_price !== null ? (string) $costMovement->cost_price : null,
                    'package_unit_cost_estimate' => $packageUnitCostEstimate,
                    'product_variant' => $plan->productVariant ? [
                        'id' => $plan->productVariant->id,
                        'presentation_label' => $plan->productVariant->presentation_label,
                        'presentation_value' => $presentationValue,
                    ] : null,
                ];
            })->values(),
            'line_adjustments' => $productionOrder->lineAdjustments->map(fn (ProductionOrderLineAdjustment $adj) => [
                'id' => $adj->id,
                'raw_material_id' => (int) $adj->raw_material_id,
                'quantity' => (float) $adj->quantity,
                'reason' => $adj->reason,
                'notes' => $adj->notes,
                'created_at' => $adj->created_at?->toISOString(),
                'raw_material' => $adj->rawMaterial ? [
                    'id' => $adj->rawMaterial->id,
                    'code' => $adj->rawMaterial->code,
                ] : null,
            ])->values(),
        ];
    }
}
