<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasTone;

enum QuotationStatus: string implements HasTone
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Borrador'),
            self::Sent => __('Enviado'),
            self::Accepted => __('Aceptado'),
            self::Rejected => __('Rechazado'),
        };
    }

    public function tone(): Tone
    {
        return match ($this) {
            self::Draft => Tone::Neutral,
            self::Sent => Tone::Info,
            self::Accepted => Tone::Success,
            self::Rejected => Tone::Danger,
        };
    }
}
