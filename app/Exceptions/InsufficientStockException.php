<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;

class InsufficientStockException extends ValidationException
{
    public static function insufficientQuantity(
        string $available,
        string $requested
    ): self {
        return self::withMessages([
            'quantity' => __('Stock insuficiente en bodega. Disponible: :available, Solicitado: :requested.', [
                'available' => $available,
                'requested' => $requested,
            ]),
        ]);
    }
}
