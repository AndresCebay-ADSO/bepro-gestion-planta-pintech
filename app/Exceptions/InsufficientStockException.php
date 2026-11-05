<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\NumberFormat;
use Illuminate\Validation\ValidationException;

class InsufficientStockException extends ValidationException
{
    public static function insufficientQuantity(
        string $available,
        string $requested
    ): self {
        return self::withMessages([
            'quantity' => __('Stock insuficiente en bodega. Disponible: :available, Solicitado: :requested.', [
                'available' => NumberFormat::quantity($available),
                'requested' => NumberFormat::quantity($requested),
            ]),
        ]);
    }
}
