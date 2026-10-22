<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Enums\NumberType;
use App\Enums\ProductionOrderStatus;
use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\ProductionOrderPackagingPlan;
use App\Models\Warehouse;
use App\Services\DecimalCalculator;
use App\Services\FormulaService;
use App\Services\Inventory\FifoStockAllocatorService;
use App\Services\TimezoneService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateProductionOrderAction
{
    public function __construct(
        private readonly FifoStockAllocatorService $fifoStockAllocator,
        private readonly FormulaService $formulaService,
        private readonly DecimalCalculator $calculator,
        private readonly TimezoneService $timezone,
    ) {}

    /**
     * @param  array{
     *   product_id:int,
     *   formula_id:int,
     *   warehouse_id:int,
     *   quantity:float|int|string,
     *   planned_date:mixed,
     *   notes?:string|null,
     *   color?:string|null,
     *   packaging?:array<int, array{product_variant_id:int, planned_units:float|int}>
     * }  $data
     */
    public function execute(array $data, int $userId): ProductionOrder
    {
        return DB::transaction(function () use ($data, $userId): ProductionOrder {
            $quantity = (string) $data['quantity'];
            $warehouseId = (int) $data['warehouse_id'];

            // Bloqueo compartido: si alguien desactiva el producto o la bodega a la vez (lockForUpdate sobre la misma
            // fila), una de las dos operaciones espera a la otra y ve su resultado (docs/POLITICA_ELIMINACION.md §3.1).
            $this->ensureStillActive((int) $data['product_id'], $warehouseId);

            // La fórmula y sus líneas se leen después del bloqueo del producto: desactivarla o editar sus líneas también
            // lo bloquean (acciones de fórmulas), así que la orden ve lo ya guardado y no una versión vieja.
            $formula = $this->stillActiveFormula((int) $data['formula_id'], (int) $data['product_id']);

            $this->fifoStockAllocator->validateStockForOrder($formula, $quantity, $warehouseId);

            $order = ProductionOrder::create([
                'product_id' => $data['product_id'],
                'formula_id' => $data['formula_id'],
                'warehouse_id' => $warehouseId,
                'quantity' => $quantity,
                'planned_date' => $data['planned_date'],
                'notes' => $data['notes'] ?? null,
                'color' => $data['color'] ?? null,
                'order_number' => $this->generateOrderNumber(),
                'lot_number' => $this->generateLotNumber(),
                'status' => ProductionOrderStatus::Pending,
                'created_by' => $userId,
            ]);

            $requirementsByMaterialId = [];
            $computedDetails = [];

            foreach ($formula->details as $detail) {
                $materialId = (int) $detail->raw_material_id;
                ['planned_quantity' => $detailTotal, 'conversion_factor' => $factor] = $this->formulaService->orderLineRequirement($detail, $quantity);

                // La fórmula por galón × los galones de la OP: cada uno cabe en su regla, el producto puede no caber.
                // Una MP sin control de inventario (agua) no tiene stock que lo frene antes.
                NumberType::Quantity->ensureFits(
                    $detailTotal,
                    'quantity',
                    __('La cantidad de :code para esta orden no cabe en el registro. Revisa la cantidad a producir.', [
                        'code' => $detail->rawMaterial->code,
                    ]),
                );

                $requirementsByMaterialId[$materialId] = isset($requirementsByMaterialId[$materialId])
                    ? $this->calculator->add($requirementsByMaterialId[$materialId], $detailTotal, 4)
                    : $detailTotal;

                $computedDetails[] = [
                    'detail' => $detail,
                    'planned_quantity' => $detailTotal,
                    'conversion_factor' => $factor,
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
                $totalCost = $this->calculator->mul($plannedQuantityStr, $estimatedUnitCost, 4);

                NumberType::Money->ensureFits(
                    $totalCost,
                    'quantity',
                    __('El costo de :code para esta orden no cabe en el registro. Revisa la cantidad a producir.', [
                        'code' => $detail->rawMaterial->code,
                    ]),
                );

                ProductionOrderDetail::create([
                    'production_order_id' => $order->id,
                    'raw_material_id' => $detail->raw_material_id,
                    'batch_id' => null,
                    'step_order' => $detail->step_order,
                    'planned_quantity' => $plannedQuantityStr,
                    // Se guarda la equivalencia usada: la OP no depende de que la unidad cambie en el catálogo.
                    'conversion_factor' => $computed['conversion_factor'],
                    'unit_cost' => $estimatedUnitCost,
                    'total_cost' => $totalCost,
                ]);
            }

            foreach (($data['packaging'] ?? []) as $packData) {
                ProductionOrderPackagingPlan::createForVariant((int) $order->id, (int) $packData['product_variant_id'], $packData['planned_units']);
            }

            return $order;
        }, attempts: 3);
    }

    private function generateOrderNumber(): string
    {
        // Año de la planta: el 31-dic después de las 7 p. m. ya es 1-ene en UTC (auditoría 3.6, OPC-04).
        $year = $this->timezone->nowInPlant()->year;
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

    private function stillActiveFormula(int $formulaId, int $productId): Formula
    {
        $formula = Formula::query()
            ->whereKey($formulaId)
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->with(['details.unitOfMeasure', 'details.rawMaterial.unitOfMeasure'])
            ->first();

        if ($formula === null) {
            throw ValidationException::withMessages(['formula_id' => __('La fórmula se desactivó mientras se creaba la orden.')]);
        }

        return $formula;
    }

    private function ensureStillActive(int $productId, int $warehouseId): void
    {
        $productActive = Product::query()->whereKey($productId)->where('is_active', true)->sharedLock()->first(['id']) !== null;
        $warehouseActive = Warehouse::query()->whereKey($warehouseId)->where('is_active', true)->sharedLock()->first(['id']) !== null;

        if (! $productActive) {
            throw ValidationException::withMessages(['product_id' => __('El producto se desactivó mientras se creaba la orden.')]);
        }

        if (! $warehouseActive) {
            throw ValidationException::withMessages(['warehouse_id' => __('La bodega se desactivó mientras se creaba la orden.')]);
        }
    }
}
