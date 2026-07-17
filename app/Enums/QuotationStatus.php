<?php

declare(strict_types=1);

namespace App\Enums;

enum QuotationStatus: string
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
}
