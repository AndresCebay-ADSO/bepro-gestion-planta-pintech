<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Enums\ProductionOrderStatus;
use App\Models\ProductionOrder;
use App\Models\RemnantConsumption;
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

            // La policy de cancelar no mira el estado, a propósito: así el usuario ve este aviso y no un 403.
            if (! in_array($lockedOrder->status, ProductionOrderStatus::cancellable(), true)) {
                throw new \DomainException(__($cannotCancel, ['status' => $lockedOrder->status->label()]));
            }

            $parts = array_filter([
                $lockedOrder->notes,
                $reason !== null && $reason !== '' ? "Cancelación: {$reason}" : null,
                $this->consumedRemnantsNote($lockedOrder),
            ]);
            $notes = $parts === [] ? null : trim(implode("\n\n", $parts));

            $lockedOrder->update([
                'status' => ProductionOrderStatus::Cancelled,
                'notes' => $notes,
            ]);

            // Si ya se imprimieron estampitas, su QR deja de abrir el lote.
            $this->qrCodeService->deactivateFor($lockedOrder);

            return $lockedOrder->refresh();
        }, attempts: 3);
    }

    /**
     * Los saldos que esta orden ya había consumido no vuelven al inventario (comportamiento provisional hasta que los
     * saldos se apliquen al completar, B66): se dejan escritos en la nota para que no se pierdan de vista.
     */
    private function consumedRemnantsNote(ProductionOrder $order): ?string
    {
        $consumptions = $order->remnantConsumptions()->with('remnant.sourceOrder:id,order_number')->get();

        if ($consumptions->isEmpty()) {
            return null;
        }

        $lines = $consumptions->map(function (RemnantConsumption $consumption): string {
            $gallons = rtrim(rtrim((string) $consumption->quantity_gallons, '0'), '.');

            return "{$consumption->remnant?->sourceOrder?->order_number} {$gallons} gal";
        });

        return 'Saldos consumidos que no vuelven al inventario: '.$lines->implode('; ').'.';
    }
}
