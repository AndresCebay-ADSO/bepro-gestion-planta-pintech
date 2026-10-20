<?php

declare(strict_types=1);

namespace App\Services\Production;

use App\Models\ProductionOrder;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Todo lo que escribe en una OP abierta pasa por aquí: bloquea la fila de la orden y vuelve a preguntar a la policy con
 * el estado ya bloqueado. La policy de la petición se evaluó antes de esperar el bloqueo, y mientras otra petición lo
 * tenía (completar, enviar a revisión, cancelar) el estado pudo cambiar: sin esto, agregar una presentación entraba en
 * una orden ya completada (OPL-02, OPX-03). La policy es la única fuente de las reglas de estado.
 */
class ProductionOrderLock
{
    /**
     * Bloquea la OP dentro de la transacción de quien llama. Si la habilidad ya no se cumple, DomainException con el
     * mensaje dado (`:status` se reemplaza por el estado actual), que el controlador muestra como aviso.
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

        if (Gate::forUser($user)->denies($ability, $lockedOrder)) {
            throw new \DomainException(__($deniedMessage, ['status' => $lockedOrder->status->label()]));
        }

        return $lockedOrder;
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
