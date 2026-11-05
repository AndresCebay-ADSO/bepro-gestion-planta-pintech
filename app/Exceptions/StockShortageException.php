<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * La orden pide más materia prima de la que hay y nadie lo ha aceptado todavía. No es un error: la transacción se revierte
 * y el formulario pregunta con estos faltantes. Si se acepta, se reenvía y la orden se crea.
 */
class StockShortageException extends RuntimeException
{
    /**
     * @param  list<array{code: string, unit: string, required: string, available: string, missing: string, has_reference_price: bool}>  $shortages
     */
    public function __construct(public readonly array $shortages)
    {
        parent::__construct('La orden pide más materia prima de la que hay en la bodega.');
    }
}
