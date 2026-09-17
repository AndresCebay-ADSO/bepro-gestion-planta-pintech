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

    /**
     * Permiso del módulo al que pertenece la alerta; se exige además de alerts.view (Alert::scopeVisibleTo).
     * Sin `default`: un tipo nuevo obliga a decidir quién lo ve.
     */
    public function requiredPermission(): Permission
    {
        return match ($this) {
            self::StockBajo,
            self::VencimientoProximo => Permission::RawMaterialsView,
            // El mensaje lleva el precio anterior y el nuevo de la materia prima: es costo.
            self::VariacionPrecio => Permission::CostsView,
            // El mensaje lleva el cliente de una solicitud que puede no ser propia.
            self::PaintDevelopmentRequest => Permission::PaintDevelopmentRequestsViewAll,
        };
    }
}
