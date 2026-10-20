<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Enums\ProductionOrderStatus;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\Production\ProductionOrderLock;
use App\Services\ProductionOrderQrCodeService;
use Illuminate\Support\Facades\DB;

class CancelProductionOrderAction
{
    public function __construct(
        private readonly ProductionOrderQrCodeService $qrCodeService,
        private readonly ProductionOrderLock $orderLock,
    ) {}

    public function execute(ProductionOrder $order, User $user, ?string $reason = null): ProductionOrder
    {
        return DB::transaction(function () use ($order, $user, $reason): ProductionOrder {
            $cannotCancel = "No se puede cancelar una orden en estado ':status'.";
            $lockedOrder = $this->orderLock->lockFor($order, $user, 'cancel', $cannotCancel);

            // La policy de cancelar no mira el estado, a propósito: así el usuario ve este aviso y no un 403. Los estados
            // pasan a la policy con el botón «Cancelar orden» (D-OPX2, PR 10).
            $allowedForCancellation = [
                ProductionOrderStatus::Pending,
                ProductionOrderStatus::InProgress,
            ];

            if (! in_array($lockedOrder->status, $allowedForCancellation, true)) {
                throw new \DomainException(__($cannotCancel, ['status' => $lockedOrder->status->label()]));
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

            // Si ya se imprimieron estampitas, su QR deja de abrir el lote.
            $this->qrCodeService->deactivateFor($lockedOrder);

            return $lockedOrder->refresh();
        }, attempts: 3);
    }
}
