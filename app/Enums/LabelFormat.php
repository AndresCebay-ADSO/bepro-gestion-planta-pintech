<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Formatos de estampita de lote. Cada rollo es un caso con su plantilla: un tamaño nuevo es un caso más, sin editor ni
 * configuración. Mientras haya un solo formato no se elige al imprimir.
 */
enum LabelFormat: string
{
    /** Rollo DYMO 30334 (57×32 mm, térmico directo). */
    case Dymo57x32 = 'dymo_57x32';

    private const POINTS_PER_MM = 72 / 25.4;

    public function label(): string
    {
        return match ($this) {
            self::Dymo57x32 => __('DYMO 57×32 mm'),
        };
    }

    public function view(): string
    {
        return match ($this) {
            self::Dymo57x32 => 'pdf.labels.dymo-57x32',
        };
    }

    public function widthMm(): float
    {
        return match ($this) {
            self::Dymo57x32 => 57.0,
        };
    }

    public function heightMm(): float
    {
        return match ($this) {
            self::Dymo57x32 => 32.0,
        };
    }

    /**
     * Margen de la plantilla por lado: lo que queda fuera es la zona útil.
     */
    public function marginMm(): float
    {
        return match ($this) {
            self::Dymo57x32 => 2.0,
        };
    }

    /**
     * Ancho útil en puntos, el que ocupa el nombre del producto.
     */
    public function contentWidthPt(): float
    {
        return ($this->widthMm() - 2 * $this->marginMm()) * self::POINTS_PER_MM;
    }

    /**
     * Papel para DomPDF: `[0, 0, ancho, alto]` en puntos.
     *
     * @return array{0: int, 1: int, 2: float, 3: float}
     */
    public function paper(): array
    {
        return [0, 0, $this->widthMm() * self::POINTS_PER_MM, $this->heightMm() * self::POINTS_PER_MM];
    }
}
