<?php

namespace App\Enums;

enum AlertType: string
{
    case StockBajo = 'stock_bajo';
    case VencimientoProximo = 'vencimiento_proximo';
    case VariacionPrecio = 'variacion_precio';
    case PaintDevelopmentRequest = 'paint_development_request';

    public function label(): string
    {
        return match ($this) {
            self::StockBajo => __('Stock bajo'),
            self::VencimientoProximo => __('Vencimiento próximo'),
            self::VariacionPrecio => __('Variación de precio'),
            self::PaintDevelopmentRequest => __('Solicitud de desarrollo de pintura'),
        };
    }
}
