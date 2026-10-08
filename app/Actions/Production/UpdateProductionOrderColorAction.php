<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Enums\ProductionOrderStatus;
use App\Models\ProductionOrder;
use Illuminate\Support\Facades\DB;

/**
 * Corrige el color que pidió el cliente (3.4) mientras la orden está abierta. Al completarla se congela: el certificado
 * y su nombre de archivo ya se guardaron con el nombre de ese momento. El cambio queda en la auditoría (`logOnly`).
 */
class UpdateProductionOrderColorAction
{
    public function execute(ProductionOrder $order, ?string $color): ProductionOrder
    {
        return DB::transaction(function () use ($order, $color): ProductionOrder {
            // Con la fila bloqueada: si otra petición completa o cancela la orden a la vez, una espera a la otra y esta
            // ve el estado final.
            $lockedOrder = ProductionOrder::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            if (! in_array($lockedOrder->status, ProductionOrderStatus::open(), true)) {
                throw new \DomainException(
                    "No se puede cambiar el color de una orden en estado '{$lockedOrder->status->label()}'."
                );
            }

            $lockedOrder->update(['color' => $color]);

            return $lockedOrder;
        }, attempts: 3);
    }
}
