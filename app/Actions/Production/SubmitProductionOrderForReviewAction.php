<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Enums\ProductionOrderStatus;
use App\Models\ProductionOrder;
use Illuminate\Support\Facades\DB;

class SubmitProductionOrderForReviewAction
{
    public function __construct(
        private readonly SaveProductionOrderOperationalDataAction $saveOperationalData,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(ProductionOrder $order, array $data, int $userId): ProductionOrder
    {
        return DB::transaction(function () use ($order, $data, $userId): ProductionOrder {
            $lockedOrder = ProductionOrder::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            $allowedForSubmission = [
                ProductionOrderStatus::Pending,
                ProductionOrderStatus::InProgress,
            ];

            if (! in_array($lockedOrder->status, $allowedForSubmission, true)) {
                throw new \DomainException(
                    "No se puede enviar a revisión una orden en estado '{$lockedOrder->status->label()}'."
                );
            }

            $this->saveOperationalData->execute($lockedOrder, $data);

            $lockedOrder->update([
                'status' => ProductionOrderStatus::PendingReview,
                'submitted_by' => $userId,
                'submitted_at' => now(),
                'rejection_reason' => null,
            ]);

            return $lockedOrder->refresh();
        });
    }
}
