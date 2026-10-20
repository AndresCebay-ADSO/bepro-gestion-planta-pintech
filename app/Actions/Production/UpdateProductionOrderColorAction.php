<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\Production\ProductionOrderLock;
use Illuminate\Support\Facades\DB;

/**
 * Corrige el color que pidió el cliente (3.4) mientras la orden está abierta. Al completarla se congela: el certificado
 * y su nombre de archivo ya se guardaron con el nombre de ese momento. El cambio queda en la auditoría (`logOnly`).
 */
class UpdateProductionOrderColorAction
{
    public function __construct(
        private readonly ProductionOrderLock $orderLock,
    ) {}

    public function execute(ProductionOrder $order, ?string $color, User $user): ProductionOrder
    {
        return DB::transaction(function () use ($order, $color, $user): ProductionOrder {
            // Con la fila bloqueada: si otra petición completa o cancela la orden a la vez, una espera a la otra y esta
            // ve el estado final.
            $lockedOrder = $this->orderLock->lockFor($order, $user, 'updateColor', "No se puede cambiar el color de una orden en estado ':status'.");

            $lockedOrder->update(['color' => $color]);

            return $lockedOrder;
        }, attempts: 3);
    }
}
