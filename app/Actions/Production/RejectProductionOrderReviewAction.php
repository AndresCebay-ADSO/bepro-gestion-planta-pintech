<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Enums\ProductionOrderStatus;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\Production\ProductionOrderLock;
use Illuminate\Support\Facades\DB;

class RejectProductionOrderReviewAction
{
    public function __construct(
        private readonly ProductionOrderLock $orderLock,
    ) {}

    public function execute(ProductionOrder $order, string $reason, User $user): ProductionOrder
    {
        return DB::transaction(function () use ($order, $reason, $user): ProductionOrder {
            $lockedOrder = $this->orderLock->lockFor($order, $user, 'rejectReview', "Solo se pueden devolver órdenes en estado 'Pendiente de revisión'.");

            $lockedOrder->update([
                'status' => ProductionOrderStatus::InProgress,
                'rejection_reason' => $reason,
            ]);

            return $lockedOrder->refresh();
        });
    }
}
