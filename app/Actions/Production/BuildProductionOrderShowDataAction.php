<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Enums\ProductionOrderStatus;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\ProductionOrderLineAdjustment;
use App\Models\ProductionOrderPackagingPlan;
use App\Models\ProductionRemnant;
use App\Models\RemnantConsumption;
use App\Services\DecimalCalculator;
use App\Services\FormulaService;
use App\Services\Inventory\FifoStockAllocatorService;

class BuildProductionOrderShowDataAction
{
    public function __construct(
        private readonly FifoStockAllocatorService $fifoStockAllocator,
        private readonly DecimalCalculator $calculator,
        private readonly FormulaService $formulaService
    ) {}

    /**
     * Carga relaciones y transforma la orden en un array para la pantalla Inertia.
     *
     * @return array<string, mixed>
     */
    public function execute(ProductionOrder $productionOrder, bool $includeCosts = true): array
    {
        $productionOrder->load([
            'product',
            'qrCode',
            'remnant',
            'remnantConsumptions.remnant.sourceOrder',
            'remnantConsumptions.consumedBy',
            'formula.details.rawMaterial',
            'formula.details.unitOfMeasure',
            'details.rawMaterial.unitOfMeasure',
            'packagingPlans.productVariant.packageRawMaterial',
            'finishedInventoryMovements',
            'warehouse',
            'lineAdjustments.rawMaterial',
            'submittedBy:id,name',
            'reviewedBy:id,name',
            'qualityResponsibleUser:id,name,job_title',
        ]);

        $formulaDetailsByKey = collect();
        if ($productionOrder->formula) {
            $formulaDetailsByKey = $productionOrder->formula->details
                ->mapWithKeys(fn ($fd) => [$fd->step_order.'-'.$fd->raw_material_id => $fd]);
        }

        $finishedCostByVariant = $includeCosts
            ? $productionOrder->finishedInventoryMovements->keyBy('product_variant_id')
            : collect();

        $packageUnitCostEstimates = [];

        if ($includeCosts) {
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
        }

        $totalFinishedCostStr = '0';
        $totalBulkCostStr = '0';

        if ($includeCosts) {
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
        }

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
            'lot_number' => $productionOrder->lot_number,
            'status' => $productionOrder->status->value,
            'quantity' => (float) $productionOrder->quantity,
            'actual_quantity' => $productionOrder->actual_quantity !== null ? (float) $productionOrder->actual_quantity : null,
            'yield_real_quantity' => $productionOrder->yield_real_quantity !== null ? (float) $productionOrder->yield_real_quantity : null,
            'yield_theoretical_quantity' => $productionOrder->yield_theoretical_quantity !== null ? (float) $productionOrder->yield_theoretical_quantity : null,
            'yield_variance_quantity' => $productionOrder->yield_variance_quantity !== null ? (float) $productionOrder->yield_variance_quantity : null,
            'yield_percentage' => $productionOrder->yield_percentage !== null ? (float) $productionOrder->yield_percentage : null,
            'planned_date' => optional($productionOrder->planned_date)->toDateString(),
            'completion_date' => optional($productionOrder->completion_date)->toDateString(),
            'viscosity_ku' => $productionOrder->viscosity_ku !== null ? (float) $productionOrder->viscosity_ku : null,
            'grinding_hg' => $productionOrder->grinding_hg !== null ? (float) $productionOrder->grinding_hg : null,
            'quality_solids' => $productionOrder->quality_solids !== null ? (float) $productionOrder->quality_solids : null,
            'agitation_start_time' => optional($productionOrder->agitation_start_time)->toISOString(),
            'agitation_end_time' => optional($productionOrder->agitation_end_time)->toISOString(),
            'packaging_start_time' => optional($productionOrder->packaging_start_time)->toISOString(),
            'packaging_end_time' => optional($productionOrder->packaging_end_time)->toISOString(),
            'responsible_name' => $productionOrder->responsible_name,
            'spillage_quantity' => (float) $productionOrder->spillage_quantity,
            'density_kg_per_gallon' => $productionOrder->density_kg_per_gallon !== null ? (float) $productionOrder->density_kg_per_gallon : null,
            'notes' => $productionOrder->notes,
            'submitted_at' => $productionOrder->submitted_at?->toISOString(),
            'reviewed_at' => $productionOrder->reviewed_at?->toISOString(),
            'rejection_reason' => $productionOrder->rejection_reason,
            'submitted_by' => $productionOrder->submittedBy ? [
                'id' => $productionOrder->submittedBy->id,
                'name' => $productionOrder->submittedBy->name,
            ] : null,
            'reviewed_by' => $productionOrder->reviewedBy ? [
                'id' => $productionOrder->reviewedBy->id,
                'name' => $productionOrder->reviewedBy->name,
            ] : null,
            'quality_responsible_user_id' => $productionOrder->quality_responsible_user_id,
            'quality_responsible_user' => $productionOrder->qualityResponsibleUser ? [
                'id' => $productionOrder->qualityResponsibleUser->id,
                'name' => $productionOrder->qualityResponsibleUser->name,
                'job_title' => $productionOrder->qualityResponsibleUser->job_title,
            ] : null,
            'qr_landing_url' => $qrLandingUrl,
            'qr_image_url' => $qrImageUrl,
            'product' => $productionOrder->product ? [
                'id' => $productionOrder->product->id,
                'name' => $productionOrder->product->name,
                'code' => $productionOrder->product->code,
                'cif_percentage' => $productionOrder->product->cif_percentage !== null ? (float) $productionOrder->product->cif_percentage : null,
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
            ...($includeCosts ? [
                'total_bulk_cost' => $totalBulkCostStr,
                'total_finished_cost' => $totalFinishedCostStr,
            ] : []),
            'details' => $productionOrder->details->map(function (ProductionOrderDetail $detail) use ($formulaDetailsByKey, $productionOrder, $includeCosts) {
                $formulaDetail = $formulaDetailsByKey->get($detail->step_order.'-'.$detail->raw_material_id);

                $displayQuantity = null;
                $displayUnit = null;
                $conversionFactor = null;
                if ($formulaDetail && $formulaDetail->unitOfMeasure) {
                    $rawMaterialUnit = $detail->rawMaterial?->unitOfMeasure;

                    if ($rawMaterialUnit !== null) {
                        try {
                            $conversionFactor = (float) $this->formulaService->getConversionFactor(
                                $formulaDetail->unitOfMeasure,
                                $rawMaterialUnit
                            );

                            $displayQuantity = (float) $this->calculator->mul(
                                (string) $formulaDetail->quantity,
                                (string) $productionOrder->quantity,
                                4
                            );
                            $displayUnit = $formulaDetail->unitOfMeasure->symbol;
                        } catch (\DomainException) {
                            $conversionFactor = null;
                        }
                    }
                }

                $row = [
                    'id' => $detail->id,
                    'raw_material_id' => (int) $detail->raw_material_id,
                    'step_order' => (int) $detail->step_order,
                    'planned_quantity' => (float) $detail->planned_quantity,
                    'display_quantity' => $displayQuantity,
                    'display_unit' => $displayUnit,
                    'conversion_factor' => $conversionFactor,
                    'actual_quantity' => $detail->actual_quantity !== null ? (float) $detail->actual_quantity : null,
                    'raw_material' => $detail->rawMaterial ? [
                        'id' => $detail->rawMaterial->id,
                        'code' => $detail->rawMaterial->code,
                        'unit_symbol' => $detail->rawMaterial->unitOfMeasure?->symbol,
                    ] : null,
                ];

                if ($includeCosts) {
                    $row['unit_cost'] = (string) $detail->unit_cost;
                    $row['total_cost'] = (string) $detail->total_cost;
                }

                return $row;
            })->values(),
            'packaging_plans' => $productionOrder->packagingPlans->map(function (ProductionOrderPackagingPlan $plan) use ($finishedCostByVariant, $packageUnitCostEstimates, $includeCosts) {
                $presentationValue = (float) ($plan->productVariant?->presentation_value ?? 1);

                $row = [
                    'id' => $plan->id,
                    'planned_units' => (float) $plan->planned_units,
                    'actual_units' => $plan->actual_units !== null ? (float) $plan->actual_units : null,
                    'product_variant' => $plan->productVariant ? [
                        'id' => $plan->productVariant->id,
                        'presentation_label' => $plan->productVariant->presentation_label,
                        'presentation_value' => $presentationValue,
                    ] : null,
                ];

                if ($includeCosts) {
                    $costMovement = $finishedCostByVariant->get($plan->product_variant_id);
                    $packageRawMaterialId = $plan->productVariant?->package_raw_material_id;
                    $packageUnitCostEstimate = $packageRawMaterialId !== null
                        ? (string) ($packageUnitCostEstimates[(int) $packageRawMaterialId] ?? '0')
                        : null;

                    $row['cost_price'] = $costMovement?->cost_price !== null ? (string) $costMovement->cost_price : null;
                    $row['package_unit_cost_estimate'] = $packageUnitCostEstimate;
                }

                return $row;
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
            'remnant' => $productionOrder->remnant ? [
                'id' => $productionOrder->remnant->id,
                'original_quantity_gallons' => (float) $productionOrder->remnant->original_quantity_gallons,
                'available_quantity_gallons' => (float) $productionOrder->remnant->available_quantity_gallons,
                'original_quantity_kg' => (float) $productionOrder->remnant->original_quantity_kg,
                'available_quantity_kg' => (float) $productionOrder->remnant->available_quantity_kg,
                'density_kg_per_gallon' => (float) $productionOrder->remnant->density_kg_per_gallon,
                'cost_per_gallon' => $productionOrder->remnant->cost_per_gallon !== null ? (float) $productionOrder->remnant->cost_per_gallon : null,
                'status' => $productionOrder->remnant->status->value,
                'status_label' => $productionOrder->remnant->status->label(),
            ] : null,
            'remnant_consumptions' => $productionOrder->remnantConsumptions->map(fn (RemnantConsumption $consumption) => [
                'id' => $consumption->id,
                'remnant_id' => $consumption->remnant_id,
                'source_order_number' => $consumption->remnant?->sourceOrder?->order_number,
                'quantity_gallons' => (float) $consumption->quantity_gallons,
                'quantity_kg' => (float) $consumption->quantity_kg,
                'consumed_cost' => $consumption->consumed_cost !== null ? (float) $consumption->consumed_cost : null,
                'notes' => $consumption->notes,
                'consumed_at' => $consumption->consumed_at->toISOString(),
                'consumed_by' => $consumption->consumedBy ? [
                    'id' => $consumption->consumedBy->id,
                    'name' => $consumption->consumedBy->name,
                ] : null,
            ])->values(),
            'available_remnants' => $productionOrder->status === ProductionOrderStatus::InProgress
                ? ProductionRemnant::query()
                    ->with(['sourceOrder:id,order_number'])
                    ->available()
                    ->where('warehouse_id', $productionOrder->warehouse_id)
                    ->orderBy('created_at', 'asc')
                    ->limit(50)
                    ->get()
                    ->map(fn (ProductionRemnant $r) => [
                        'id' => $r->id,
                        'source_order_number' => $r->sourceOrder->order_number,
                        'available_quantity_gallons' => (float) $r->available_quantity_gallons,
                        'density_kg_per_gallon' => (float) $r->density_kg_per_gallon,
                    ])->values()
                : [],
        ];
    }
}
