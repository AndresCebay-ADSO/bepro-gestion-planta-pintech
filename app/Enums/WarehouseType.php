<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasTone;

enum WarehouseType: string implements HasTone
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

    /**
     * Tono de la pastilla (DESIGN.md §4). Son tipos, no estados: todos neutros; se distinguen por la etiqueta.
     */
    public function tone(): Tone
    {
        return match ($this) {
            self::Factory,
            self::Storage => Tone::Neutral,
        };
    }
}
