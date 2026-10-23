<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasTone;

enum AlertType: string implements HasTone
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
     * Tono de la pastilla (DESIGN.md §4). Son tipos, no estados: todos neutros; se distinguen por icono y etiqueta.
     */
    public function tone(): Tone
    {
        return match ($this) {
            self::StockBajo,
            self::VencimientoProximo,
            self::VariacionPrecio,
            self::PaintDevelopmentRequest => Tone::Neutral,
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
