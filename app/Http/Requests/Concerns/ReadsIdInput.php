<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Para usar un ID enviado dentro de otra regla (el `exists` de la fórmula filtra por el producto elegido) o de un
 * `after()`, que corren aunque ese ID ya tenga su error. Crudo, «abc» o un número mayor que un bigint llegan a la
 * consulta y PostgreSQL responde con un 500 (auditoría 3.6: OPC-06, PV-04).
 */
trait ReadsIdInput
{
    /** El ID enviado en `$key`, o 0 si no es un entero válido: 0 no coincide con ningún registro. */
    protected function idInput(string $key): int
    {
        $id = filter_var($this->input($key), FILTER_VALIDATE_INT);

        return $id === false ? 0 : $id;
    }
}
