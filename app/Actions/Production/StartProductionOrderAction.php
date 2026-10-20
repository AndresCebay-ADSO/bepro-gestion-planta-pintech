<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Enums\ProductionOrderStatus;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\Production\ProductionOrderLock;
use Illuminate\Support\Facades\DB;

class StartProductionOrderAction
{
    public function __construct(
        private readonly ProductionOrderLock $orderLock,
    ) {}

    public function execute(ProductionOrder $order, User $user): ProductionOrder
    {
        return DB::transaction(function () use ($order, $user): ProductionOrder {
            $lockedOrder = $this->orderLock->lockFor($order, $user, 'startProduction', "No se puede iniciar producción de una orden en estado ':status'.");

            $lockedOrder->update([
                'status' => ProductionOrderStatus::InProgress,
            ]);

            return $lockedOrder->refresh();
        });
    }
}
