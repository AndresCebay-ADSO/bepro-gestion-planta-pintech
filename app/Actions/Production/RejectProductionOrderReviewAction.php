<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Enums\ProductionOrderStatus;
use App\Models\ProductionOrder;
use Illuminate\Support\Facades\DB;

class RejectProductionOrderReviewAction
{
    public function execute(ProductionOrder $order, string $reason, int $userId): ProductionOrder
    {
        return DB::transaction(function () use ($order, $reason, $userId): ProductionOrder {
            $lockedOrder = ProductionOrder::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($lockedOrder->status !== ProductionOrderStatus::PendingReview) {
                throw new \DomainException(
                    "Solo se pueden devolver órdenes en estado 'Pendiente de revisión'."
                );
            }

            $lockedOrder->update([
                'status' => ProductionOrderStatus::InProgress,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            return $lockedOrder->refresh();
        });
    }
}
