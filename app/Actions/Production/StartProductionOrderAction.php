<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Enums\ProductionOrderStatus;
use App\Models\ProductionOrder;
use Illuminate\Support\Facades\DB;

class StartProductionOrderAction
{
    public function execute(ProductionOrder $order): ProductionOrder
    {
        return DB::transaction(function () use ($order): ProductionOrder {
            $lockedOrder = ProductionOrder::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($lockedOrder->status !== ProductionOrderStatus::Pending) {
                throw new \DomainException(
                    "No se puede iniciar producción de una orden en estado '{$lockedOrder->status->label()}'."
                );
            }

            $lockedOrder->update([
                'status' => ProductionOrderStatus::InProgress,
            ]);

            return $lockedOrder->refresh();
        });
    }
}
