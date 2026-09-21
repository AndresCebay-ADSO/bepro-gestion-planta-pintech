<?php

declare(strict_types=1);

namespace App\Actions\Shared;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Elimina físicamente un registro solo si nunca se usó (docs/POLITICA_ELIMINACION.md §4).
 *
 * No comprueba relaciones a mano: intenta el borrado y deja que las claves foráneas `RESTRICT` decidan. Si alguna
 * tabla referencia el registro, la base de datos lo rechaza, la transacción se revierte y la Action devuelve `false`
 * para que el controlador ofrezca desactivarlo. Una tabla nueva queda protegida por su propia clave foránea.
 */
class DeleteUnusedRecordAction
{
    /**
     * @param  (Closure(Model): void)|null  $beforeDelete  Borra, en la misma transacción, los hijos sin historial que
     *                                                     la clave foránea ya no elimina en cascada.
     * @return bool `true` si se eliminó; `false` si tiene historial.
     */
    public function execute(Model $record, ?Closure $beforeDelete = null): bool
    {
        try {
            DB::transaction(function () use ($record, $beforeDelete): void {
                $locked = $record->newQuery()->lockForUpdate()->findOrFail($record->getKey());

                if ($beforeDelete !== null) {
                    $beforeDelete($locked);
                }

                $locked->delete();
            });
        } catch (QueryException $exception) {
            if ($this->isForeignKeyViolation($exception)) {
                return false;
            }

            throw $exception;
        }

        return true;
    }

    /**
     * PostgreSQL devuelve SQLSTATE 23503; SQLite (tests) devuelve 23000 con "FOREIGN KEY constraint failed".
     */
    private function isForeignKeyViolation(QueryException $exception): bool
    {
        return (string) $exception->getCode() === '23503'
            || str_contains($exception->getMessage(), 'FOREIGN KEY constraint failed');
    }
}
