<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Enums\ProductionOrderStatus;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\ProductionOrderLineAdjustment;
use App\Models\ProductionOrderPackagingPlan;
use App\Models\RemnantConsumption;
use App\Services\AvailableRemnantsService;
use App\Services\DecimalCalculator;

class BuildProductionOrderShowDataAction
{
    public function __construct(
        private readonly DecimalCalculator $calculator,
        private readonly AvailableRemnantsService $availableRemnants,
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
            'remnantConsumptions.remnant.product:id,name',
            'remnantConsumptions.consumedBy',
            'formula.details.rawMaterial',
            'formula.details.unitOfMeasure',
            'details.rawMaterial.unitOfMeasure',
            'packagingPlans.productVariant.packageRawMaterial',
            'packagingPlans.packageRawMaterial',
            'packagingPlans.labelRawMaterial',
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
            // Color que pidió el cliente y el nombre del producto con él (3.4): pantalla, PDF y Excel usan el mismo.
            'color' => $productionOrder->color,
            'product_display_name' => $productionOrder->productDisplayName(),
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
                ...($includeCosts ? [
                    'cif_percentage' => $productionOrder->product->cif_percentage !== null ? (float) $productionOrder->product->cif_percentage : null,
                ] : []),
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
                // Se convierte con la equivalencia guardada al crear la OP, no con la actual del catálogo: así lo que
                // registra el operario no cambia si alguien edita la unidad. Sin ella, se muestra en la unidad de la MP.
                if ($formulaDetail?->unitOfMeasure !== null && $detail->conversion_factor !== null) {
                    $conversionFactor = (float) $detail->conversion_factor;
                    $displayQuantity = (float) $this->calculator->mul(
                        (string) $formulaDetail->quantity,
                        (string) $productionOrder->quantity,
                        4
                    );
                    $displayUnit = $formulaDetail->unitOfMeasure->symbol;
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
            'packaging_plans' => $productionOrder->packagingPlans->map(function (ProductionOrderPackagingPlan $plan) use ($finishedCostByVariant, $includeCosts, $productionOrder) {
                $presentationValue = (float) ($plan->productVariant?->presentation_value ?? 1);
                // Completada, vale solo el envase guardado al completar (vacío si la presentación no tenía): el documento
                // cerrado no cambia si después se cambia el de la presentación. Antes de completar, el de la presentación.
                $packageCode = $productionOrder->status === ProductionOrderStatus::Completed
                    ? $plan->packageRawMaterial?->code
                    : $plan->productVariant?->packageRawMaterial?->code;

                $row = [
                    'id' => $plan->id,
                    'planned_units' => (float) $plan->planned_units,
                    'actual_units' => $plan->actual_units !== null ? (float) $plan->actual_units : null,
                    // Empaque (3.7). Vacío = tantos como unidades envasadas. El envase es el consumido (guardado al
                    // completar) o, con la OP abierta, el de la presentación; la etiqueta es la del plan.
                    'package_code' => $packageCode,
                    'new_containers_used' => $plan->new_containers_used,
                    'label_raw_material_id' => $plan->label_raw_material_id,
                    'label_code' => $plan->labelRawMaterial?->code,
                    'labels_used' => $plan->labels_used,
                    // Para el PDF y el Excel: «GALON-MET · ETQ-GALON», o null si no lleva ninguno.
                    'packaging_materials' => collect([$packageCode, $plan->labelRawMaterial?->code])->filter()->implode(' · ') ?: null,
                    'product_variant' => $plan->productVariant ? [
                        'id' => $plan->productVariant->id,
                        'presentation_label' => $plan->productVariant->presentation_label,
                        'presentation_value' => $presentationValue,
                    ] : null,
                ];

                if ($includeCosts) {
                    $costMovement = $finishedCostByVariant->get($plan->product_variant_id);

                    $row['cost_price'] = $costMovement?->cost_price !== null ? (string) $costMovement->cost_price : null;
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
                ...($includeCosts ? [
                    'cost_per_gallon' => $productionOrder->remnant->cost_per_gallon !== null ? (float) $productionOrder->remnant->cost_per_gallon : null,
                ] : []),
                'status' => $productionOrder->remnant->status->value,
                'status_label' => $productionOrder->remnant->status->label(),
            ] : null,
            'remnant_consumptions' => $productionOrder->remnantConsumptions->map(fn (RemnantConsumption $consumption) => [
                'id' => $consumption->id,
                'remnant_id' => $consumption->remnant_id,
                'source_order_number' => $consumption->remnant?->sourceOrder?->order_number,
                // Qué se mezcló en esta orden: el producto con el color de la orden de origen (B57).
                'source_product_name' => $consumption->remnant?->sourceOrder !== null && $consumption->remnant->product !== null
                    ? ProductionOrder::nameWithColor($consumption->remnant->product->name, $consumption->remnant->sourceOrder->color)
                    : null,
                'quantity_gallons' => (float) $consumption->quantity_gallons,
                'quantity_kg' => (float) $consumption->quantity_kg,
                ...($includeCosts ? [
                    'consumed_cost' => $consumption->consumed_cost !== null ? (float) $consumption->consumed_cost : null,
                ] : []),
                'notes' => $consumption->notes,
                'consumed_at' => $consumption->consumed_at->toISOString(),
                'consumed_by' => $consumption->consumedBy ? [
                    'id' => $consumption->consumedBy->id,
                    'name' => $consumption->consumedBy->name,
                ] : null,
            ])->values(),
            'available_remnants' => $productionOrder->status === ProductionOrderStatus::InProgress
                ? $this->availableRemnants->forOrder($productionOrder)
                : [],
        ];
    }
}
