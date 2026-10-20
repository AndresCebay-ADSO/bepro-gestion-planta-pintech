<?php

declare(strict_types=1);

namespace App\Services\Production;

use App\Models\ProductionOrder;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Lo que escribe en una OP (completar, revisión, iniciar, color, cancelar, presentaciones, ajustes, saldos y estampitas)
 * pasa por aquí: bloquea la fila de la orden y vuelve a preguntar a la policy con el estado ya bloqueado. La policy de la
 * petición se evaluó antes de esperar el bloqueo, y mientras otra petición lo tenía (completar, enviar a revisión,
 * cancelar) el estado pudo cambiar: sin esto, agregar una presentación entraba en una orden ya completada (OPL-02,
 * OPX-03). Las reglas de estado viven en la policy; la única excepción es cancelar, cuya policy no mira el estado para
 * que el usuario vea el aviso de CancelProductionOrderAction y no un 403.
 */
class ProductionOrderLock
{
    /**
     * Bloquea la OP dentro de la transacción de quien llama. `$order` es la orden que vio la policy de la petición. Si la
     * habilidad ya no se cumple porque el estado cambió mientras esperaba, DomainException con el mensaje dado
     * (`:status` se reemplaza por el estado actual), que el controlador muestra como aviso. Si el estado es el mismo, lo
     * que cambió es el permiso: 403, como lo habría respondido la policy, y no un aviso que culpa al estado.
     */
    public function lockFor(ProductionOrder $order, User $user, string $ability, string $deniedMessage): ProductionOrder
    {
        if (DB::transactionLevel() === 0) {
            // Fuera de una transacción el bloqueo se suelta en la misma consulta: no protegería nada.
            throw new \LogicException('ProductionOrderLock::lockFor debe llamarse dentro de una transacción.');
        }

        $lockedOrder = ProductionOrder::query()
            ->lockForUpdate()
            ->findOrFail($order->id);

        if (Gate::forUser($user)->allows($ability, $lockedOrder)) {
            return $lockedOrder;
        }

        if ($lockedOrder->status === $order->status) {
            throw new AuthorizationException(__('No tienes permiso para realizar esta acción.'));
        }

        throw new \DomainException(__($deniedMessage, ['status' => $lockedOrder->status->label()]));
    }

    /**
     * Abre la transacción, bloquea la OP y ejecuta la escritura con la orden bloqueada.
     *
     * @template TResult
     *
     * @param  Closure(ProductionOrder): TResult  $write
     * @return TResult
     */
    public function write(ProductionOrder $order, User $user, string $ability, string $deniedMessage, Closure $write): mixed
    {
        return DB::transaction(
            fn () => $write($this->lockFor($order, $user, $ability, $deniedMessage)),
            attempts: 3,
        );
    }
}
