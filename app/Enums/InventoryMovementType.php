<?php

namespace App\Enums;

enum InventoryMovementType: string
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
}
