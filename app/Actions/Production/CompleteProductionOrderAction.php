<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Enums\FinishedInventoryMovementReason;
use App\Enums\NumberType;
use App\Enums\ProductionOrderStatus;
use App\Enums\RemnantStatus;
use App\Jobs\GenerateQualityInspectionCertificateJob;
use App\Jobs\RecalculateRawMaterialReferencePrice;
use App\Models\FinishedProductBatch;
use App\Models\Product;
use App\Models\ProductionCost;
use App\Models\ProductionOrder;
use App\Models\ProductionRemnant;
use App\Models\User;
use App\Services\AlertService;
use App\Services\DecimalCalculator;
use App\Services\FinishedInventory\FinishedInventoryMovementService;
use App\Services\Inventory\FifoStockAllocatorService;
use App\Services\Pricing\ProductionCostCalculatorService;
use App\Services\Production\ProductionOrderCompletionLines;
use App\Services\Production\ProductionOrderLock;
use App\Services\TimezoneService;
use Illuminate\Support\Facades\DB;

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
        private readonly ProductionOrderLock $orderLock,
        private readonly ProductionOrderCompletionLines $completionLines,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(ProductionOrder $order, array $data, User $user): ProductionOrder
    {
        $userId = (int) $user->id;

        $completedOrder = DB::transaction(function () use ($order, $data, $user, $userId): ProductionOrder {
            $lockedOrder = $this->orderLock->lockFor($order, $user, 'complete', "No se puede completar una orden en estado ':status'.");

            // Con la fila bloqueada nadie agrega ni quita líneas: lo enviado debe ser exactamente lo de la orden.
            $this->completionLines->ensureMatches($lockedOrder, $data);

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

            $lockedOrder->loadMissing(['details.rawMaterial:id,code', 'packagingPlans.productVariant', 'lineAdjustments', 'remnantConsumptions']);
            // ensureMatches garantizó una cantidad real por cada línea: se recorren las de la orden, no las enviadas.
            $actualQuantities = collect($data['ingredients'])->mapWithKeys(fn (array $row) => [(int) $row['id'] => (string) $row['actual_quantity']]);
            $totalBulkCost = '0';
            $consumedRawMaterialIds = [];

            foreach ($lockedOrder->details as $detail) {
                $actualQuantity = $actualQuantities[(int) $detail->id];
                $consumedRawMaterialIds[] = (int) $detail->raw_material_id;

                $consumedCost = $this->fifoStockAllocator->consumeProductionOrderDetail(
                    order: $lockedOrder,
                    detail: $detail,
                    requiredQuantity: $actualQuantity,
                    userId: $userId,
                    movementDate: $completionDate,
                );
                // Cantidad real × precio: cada uno cabe en su regla, el producto puede no caber. Una MP sin control de
                // inventario no tiene stock que lo frene antes. `ingredients`: la clave que muestra la pantalla.
                NumberType::Money->ensureFits(
                    $consumedCost,
                    'ingredients',
                    __('El costo de :code no cabe en el registro. Revisa la cantidad real consumida.', [
                        'code' => $detail->rawMaterial?->code,
                    ]),
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

            // El total suma ingredientes, ajustes de línea y saldos consumidos: puede no caber aunque cada línea quepa. Se
            // revisa antes de repartirlo entre las presentaciones, que guardan su parte en el PT.
            NumberType::Money->ensureFits(
                $totalBulkCost,
                'ingredients',
                __('El costo total de la orden no cabe en el registro. Revisa las cantidades reales consumidas.'),
            );

            $remnantGallons = $this->completionLines->hasRemnant($data['remnant_quantity_gallons'] ?? null)
                ? (string) $data['remnant_quantity_gallons']
                : null;

            $costDistribution = $this->productionCostCalculator->calculateDistributedBulkCosts(
                order: $lockedOrder,
                packagingData: $data['packaging'] ?? [],
                totalBulkCost: $totalBulkCost,
                remnantGallons: $remnantGallons
            );
            $distributedBulkCosts = $costDistribution['distributedCosts'];
            $bulkCostPerUnit = $costDistribution['bulkCostPerUnit'];

            $productForPricing = Product::query()
                ->select(['id', 'cif_percentage'])
                ->find($lockedOrder->product_id);
            $productCifPercentage = $productForPricing?->cif_percentage !== null
                ? (string) $productForPricing->cif_percentage
                : null;

            $actualUnitsByPlan = collect($data['packaging'] ?? [])->mapWithKeys(fn (array $row) => [(int) $row['id'] => (string) $row['actual_units']]);

            foreach ($lockedOrder->packagingPlans as $plan) {
                $actualUnits = $actualUnitsByPlan[(int) $plan->id];

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

            $yieldRealQuantity = (string) $data['actual_yield_quantity'];
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
                remnantGallons: $remnantGallons,
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
     * @param  ?string  $remnantGallons  null si no quedó saldo
     * @param  array<string, mixed>  $data
     */
    private function registerRemnantIfApplicable(
        ProductionOrder $order,
        ?string $remnantGallons,
        array $data,
        ?string $bulkCostPerUnit,
        ?string $cifPercentage,
        int $userId
    ): void {
        if ($remnantGallons === null) {
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
