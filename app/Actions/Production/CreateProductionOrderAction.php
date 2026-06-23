<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Enums\ProductionOrderStatus;
use App\Models\Formula;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\ProductionOrderPackagingPlan;
use App\Services\DecimalCalculator;
use App\Services\FormulaService;
use App\Services\Inventory\FifoStockAllocatorService;
use Illuminate\Support\Facades\DB;

class CreateProductionOrderAction
{
    public function __construct(
        private readonly FifoStockAllocatorService $fifoStockAllocator,
        private readonly FormulaService $formulaService,
        private readonly DecimalCalculator $calculator
    ) {}

    /**
     * @param  array{
     *   product_id:int,
     *   formula_id:int,
     *   warehouse_id:int,
     *   quantity:float|int|string,
     *   planned_date:mixed,
     *   notes?:string|null,
     *   packaging?:array<int, array{product_variant_id:int, planned_units:float|int}>
     * }  $data
     */
    public function execute(array $data, int $userId): ProductionOrder
    {
        $formula = Formula::query()
            ->with(['details.unitOfMeasure', 'details.rawMaterial.unitOfMeasure'])
            ->findOrFail($data['formula_id']);

        return DB::transaction(function () use ($data, $formula, $userId): ProductionOrder {
            $quantity = (string) $data['quantity'];
            $warehouseId = (int) $data['warehouse_id'];

            $this->fifoStockAllocator->validateStockForOrder($formula, $quantity, $warehouseId);

            $order = ProductionOrder::create([
                'product_id' => $data['product_id'],
                'formula_id' => $data['formula_id'],
                'warehouse_id' => $warehouseId,
                'quantity' => $quantity,
                'planned_date' => $data['planned_date'],
                'notes' => $data['notes'] ?? null,
                'order_number' => $this->generateOrderNumber(),
                'lot_number' => $this->generateLotNumber(),
                'status' => ProductionOrderStatus::Pending,
                'created_by' => $userId,
            ]);

            $requirementsByMaterialId = [];
            $computedDetails = [];

            foreach ($formula->details as $detail) {
                $materialId = (int) $detail->raw_material_id;
                $factor = $this->formulaService->getConversionFactor($detail->unitOfMeasure, $detail->rawMaterial->unitOfMeasure);
                $normalizedDetailQty = $this->calculator->mul((string) $detail->quantity, $factor, 10);
                $detailTotal = $this->calculator->mul($normalizedDetailQty, (string) $quantity, 10);
                $detailTotal = $this->calculator->round($detailTotal, 4);

                $requirementsByMaterialId[$materialId] = isset($requirementsByMaterialId[$materialId])
                    ? $this->calculator->add($requirementsByMaterialId[$materialId], $detailTotal, 4)
                    : $detailTotal;

                $computedDetails[] = [
                    'detail' => $detail,
                    'planned_quantity' => $detailTotal,
                ];
            }

            $estimatedUnitCosts = $this->fifoStockAllocator->estimateMaterialUnitCostsForPlanning(
                warehouseId: $warehouseId,
                requirementsByMaterialId: $requirementsByMaterialId
            );

            foreach ($computedDetails as $computed) {
                $detail = $computed['detail'];
                $plannedQuantityStr = $computed['planned_quantity'];
                $estimatedUnitCost = (string) ($estimatedUnitCosts[(int) $detail->raw_material_id] ?? '0');

                ProductionOrderDetail::create([
                    'production_order_id' => $order->id,
                    'raw_material_id' => $detail->raw_material_id,
                    'batch_id' => null,
                    'step_order' => $detail->step_order,
                    'planned_quantity' => $plannedQuantityStr,
                    'unit_cost' => $estimatedUnitCost,
                    'total_cost' => $this->calculator->mul($plannedQuantityStr, $estimatedUnitCost, 4),
                ]);
            }

            foreach (($data['packaging'] ?? []) as $packData) {
                ProductionOrderPackagingPlan::create([
                    'production_order_id' => $order->id,
                    'product_variant_id' => $packData['product_variant_id'],
                    'planned_units' => $packData['planned_units'],
                ]);
            }

            return $order;
        }, attempts: 3);
    }

    private function generateOrderNumber(): string
    {
        $year = (int) now()->format('Y');
        $prefix = "OP-{$year}-";

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('SELECT pg_advisory_xact_lock(?, ?)', [801337, $year]);
        }

        $lastSequence = ProductionOrder::query()
            ->where('order_number', 'like', $prefix.'%')
            ->pluck('order_number')
            ->map(fn (string $orderNumber): int => (int) substr($orderNumber, strlen($prefix)))
            ->max();

        return $prefix.str_pad((string) (((int) $lastSequence) + 1), 4, '0', STR_PAD_LEFT);
    }

    private function generateLotNumber(): int
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('SELECT pg_advisory_xact_lock(?, ?)', [801338, 0]);
        }

        $startLot = (int) config('production.lot_start_number', 1);
        $maxLot = ProductionOrder::query()->max('lot_number');

        if ($maxLot === null) {
            return $startLot;
        }

        return max((int) $maxLot + 1, $startLot);
    }
}
