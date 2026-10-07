<?php

declare(strict_types=1);

namespace App\Actions\ShrinkWraps;

use App\Jobs\RecalculateRawMaterialReferencePrice;
use App\Models\ProductionOrder;
use App\Models\ShrinkWrap;
use App\Models\ShrinkWrapType;
use App\Models\ShrinkWrapTypeItem;
use App\Services\AlertService;
use App\Services\DecimalCalculator;
use App\Services\Inventory\FifoStockAllocatorService;
use App\Services\TimezoneService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra un termoencogido (3.8): copia la receta del tipo, descuenta receta × aplicaciones de la bodega de la OP
 * (FIFO, o al precio de referencia si la materia prima no controla inventario) y guarda el costo como gasto general.
 *
 * Todo o nada: si falta stock de una línea, no queda ni el registro ni ninguna salida. Las salidas se enlazan al
 * registro (`shrink_wrap_id`), no a la OP, para que ningún reporte de consumo por OP las cuente como costo del lote.
 *
 * Las salidas se fechan con el día en que se registra, no con el del termoencogido (que puede ser semanas antes): el FIFO
 * toma los lotes con saldo hoy, y fecharlas atrás podría dejar una salida anterior a la entrada de su lote. El registro
 * conserva la fecha real del termoencogido (`wrapped_at`). Así descuenta también la OP: al completarla.
 */
class RegisterShrinkWrapAction
{
    /** Tope de las columnas de cantidad: decimal(12,4). */
    private const MAX_QUANTITY = '99999999.9999';

    /** Tope de las columnas de costo: decimal(14,4). */
    private const MAX_COST = '9999999999.9999';

    public function __construct(
        private readonly FifoStockAllocatorService $fifoStockAllocator,
        private readonly DecimalCalculator $calculator,
        private readonly AlertService $alertService,
        private readonly TimezoneService $timezone,
    ) {}

    /**
     * @param  array{production_order_id: int|string, shrink_wrap_type_id: int|string, applications: int|string, wrapped_at: string, notes?: string|null}  $validated
     */
    public function execute(array $validated, int $userId): ShrinkWrap
    {
        return DB::transaction(function () use ($validated, $userId): ShrinkWrap {
            // Bloquear el tipo hace esperar a una edición simultánea de su receta: se copia la receta vigente.
            $type = ShrinkWrapType::query()->lockForUpdate()->findOrFail((int) $validated['shrink_wrap_type_id']);

            if (! $type->is_active) {
                throw ValidationException::withMessages([
                    'shrink_wrap_type_id' => __('El tipo de termoencogido no existe o está inactivo.'),
                ]);
            }

            $type->load('items.rawMaterial:id,code');

            $order = ProductionOrder::query()
                ->select(['id', 'order_number', 'warehouse_id'])
                ->findOrFail((int) $validated['production_order_id']);

            $applications = (int) $validated['applications'];
            $registeredOn = $this->timezone->todayInPlant();

            $shrinkWrap = ShrinkWrap::create([
                'production_order_id' => $order->id,
                'shrink_wrap_type_id' => $type->id,
                'warehouse_id' => $order->warehouse_id,
                'applications' => $applications,
                'wrapped_at' => $validated['wrapped_at'],
                'total_cost' => '0',
                'notes' => $validated['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $totalCost = '0';

            foreach ($type->items as $item) {
                /** @var ShrinkWrapTypeItem $item */
                $quantity = $this->calculator->mul((string) $item->quantity, (string) $applications, 4);

                // Sin tope de negocio (no se compara con las unidades producidas), pero sí técnico: lo descontado debe caber
                // en su columna. Se revisa aquí, con la receta ya bloqueada: en el request la receta podía cambiar antes
                // de guardar. Sin control de inventario no hay stock que lo frene, y sería un 500 de la base.
                if ($this->calculator->cmp($quantity, self::MAX_QUANTITY) > 0) {
                    throw ValidationException::withMessages([
                        'applications' => __('Son demasiadas aplicaciones: el consumo de :code no cabe en el registro.', [
                            'code' => $item->rawMaterial->code,
                        ]),
                    ]);
                }

                $cost = $this->fifoStockAllocator->consumeFromWarehouse(
                    rawMaterialId: $item->raw_material_id,
                    warehouseId: (int) $order->warehouse_id,
                    requiredQuantity: $quantity,
                    userId: $userId,
                    origin: ['shrink_wrap_id' => $shrinkWrap->id],
                    originLabel: "termoencogido #{$shrinkWrap->id} (OP #{$order->order_number})",
                    errorKey: 'applications',
                    contextLabel: 'empaque secundario',
                    shortageMoment: 'en la bodega de la orden',
                    movementDate: $registeredOn,
                );

                // Con stock no se llega aquí: la falta de stock lo frena antes. Sí con una materia prima sin control de
                // inventario, que no tiene stock que la frene: un costo que no cabe sería un 500 de la base.
                if ($this->calculator->cmp($cost, self::MAX_COST) > 0) {
                    throw ValidationException::withMessages([
                        'applications' => __('Son demasiadas aplicaciones: el costo de :code no cabe en el registro.', [
                            'code' => $item->rawMaterial->code,
                        ]),
                    ]);
                }

                $shrinkWrap->items()->create([
                    'raw_material_id' => $item->raw_material_id,
                    'quantity_per_application' => $item->quantity,
                    'quantity' => $quantity,
                    'total_cost' => $cost,
                ]);

                $totalCost = $this->calculator->add($totalCost, $cost, 4);
            }

            if ($this->calculator->cmp($totalCost, self::MAX_COST) > 0) {
                throw ValidationException::withMessages([
                    'applications' => __('Son demasiadas aplicaciones: el costo total no cabe en el registro.'),
                ]);
            }

            $shrinkWrap->update(['total_cost' => $totalCost]);

            $rawMaterialIds = $type->items->pluck('raw_material_id')->unique()->values();

            $rawMaterialIds->each(fn (int $id) => RecalculateRawMaterialReferencePrice::dispatch($id)->afterCommit());
            $rawMaterialIds->each(fn (int $id) => $this->alertService->evaluateLowStock($id));

            return $shrinkWrap;
        });
    }
}
