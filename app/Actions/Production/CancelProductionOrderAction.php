<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Enums\ProductionOrderStatus;
use App\Models\ProductionOrder;
use Illuminate\Support\Facades\DB;

class CancelProductionOrderAction
{
    public function execute(ProductionOrder $order, ?string $reason = null): ProductionOrder
    {
        return DB::transaction(function () use ($order, $reason): ProductionOrder {
            $lockedOrder = ProductionOrder::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            $allowedForCancellation = [
                ProductionOrderStatus::Pending,
                ProductionOrderStatus::InProgress,
            ];

            if (! in_array($lockedOrder->status, $allowedForCancellation, true)) {
                throw new \DomainException(
                    "No se puede cancelar una orden en estado '{$lockedOrder->status->label()}'."
                );
            }

            $notes = $lockedOrder->notes;
            if ($reason !== null && $reason !== '') {
                $notes = trim(implode("\n\n", array_filter([
                    $lockedOrder->notes,
                    "Cancelación: {$reason}",
                ])));
            }

            $lockedOrder->update([
                'status' => ProductionOrderStatus::Cancelled,
                'notes' => $notes,
            ]);

            return $lockedOrder->refresh();
        }, attempts: 3);
    }
}
