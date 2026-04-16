<?php

namespace App\Enums;

enum AlertType: string
{
    case StockBajo = 'stock_bajo';
    case VencimientoProximo = 'vencimiento_proximo';
    case VariacionPrecio = 'variacion_precio';

    public function label(): string
    {
        return match ($this) {
            self::StockBajo => __('Stock bajo'),
            self::VencimientoProximo => __('Vencimiento próximo'),
            self::VariacionPrecio => __('Variación de precio'),
        };
    }
}
