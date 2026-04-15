<?php

namespace App\Enums;

enum WarehouseType: string
{
    case Factory = 'factory';
    case Storage = 'storage';

    public function label(): string
    {
        return match ($this) {
            self::Factory => __('Fábrica'),
            self::Storage => __('Almacén'),
        };
    }
}
