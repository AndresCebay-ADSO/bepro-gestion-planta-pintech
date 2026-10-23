<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasTone;

enum InventoryMovementType: string implements HasTone
{
    case Entry = 'entry';
    case Exit = 'exit';

    public function label(): string
    {
        return match ($this) {
            self::Entry => __('Entrada'),
            self::Exit => __('Salida'),
        };
    }

    /**
     * Tono de la pastilla (DESIGN.md §4). Entrada y salida de materia prima y de producto terminado (la columna `type`
     * de los dos movimientos). El front la acompaña siempre de icono y signo: el color nunca va solo.
     */
    public function tone(): Tone
    {
        return match ($this) {
            self::Entry => Tone::Success,
            self::Exit => Tone::Warning,
        };
    }
}
