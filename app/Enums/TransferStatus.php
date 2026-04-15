<?php

namespace App\Enums;

enum TransferStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pendiente'),
            self::Sent => __('Enviado'),
            self::Received => __('Recibido'),
            self::Cancelled => __('Cancelado'),
        };
    }
}
