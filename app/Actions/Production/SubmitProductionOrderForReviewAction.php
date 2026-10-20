<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Enums\ProductionOrderStatus;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\Production\ProductionOrderCompletionLines;
use App\Services\Production\ProductionOrderLock;
use Illuminate\Support\Facades\DB;

class SubmitProductionOrderForReviewAction
{
    public function __construct(
        private readonly SaveProductionOrderOperationalDataAction $saveOperationalData,
        private readonly ProductionOrderLock $orderLock,
        private readonly ProductionOrderCompletionLines $completionLines,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(ProductionOrder $order, array $data, User $user): ProductionOrder
    {
        return DB::transaction(function () use ($order, $data, $user): ProductionOrder {
            $lockedOrder = $this->orderLock->lockFor($order, $user, 'submitForReview', "No se puede enviar a revisión una orden en estado ':status'.");

            // La misma lista que al completar: la lista vieja de OPL-02 se vio justamente al enviar a revisión (P6-1).
            $this->completionLines->ensureMatches($lockedOrder, $data);

            $this->saveOperationalData->execute($lockedOrder, $data);

            $lockedOrder->update([
                'status' => ProductionOrderStatus::PendingReview,
                'submitted_by' => $user->id,
                'submitted_at' => now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'rejection_reason' => null,
            ]);

            return $lockedOrder->refresh();
        });
    }
}
