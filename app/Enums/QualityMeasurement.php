<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Mediciones de calidad de una pintura. El rango de referencia del producto y lo que se mide en la OP son el mismo
 * dato, así que comparten tipo (`NumberType::Quality`, un decimal) y tope (auditoría 3.6: PRD-01, D-PRD3).
 */
enum QualityMeasurement
{
    /** Viscosidad en unidades Krebs (KU). */
    case Viscosity;

    /** Molienda en la escala Hegman, de 0 a 8. */
    case Fineness;

    /** Porcentaje de sólidos. */
    case Solids;

    public function label(): string
    {
        return match ($this) {
            self::Viscosity => __('Viscosidad'),
            self::Fineness => __('Molienda'),
            self::Solids => __('Sólidos'),
        };
    }

    public function max(): string
    {
        return match ($this) {
            self::Viscosity => NumberType::Quality->max(),
            self::Fineness => '8',
            self::Solids => '100',
        };
    }

    /**
     * @return list<string>
     */
    public function rules(): array
    {
        return ['bail', 'nullable', ...NumberType::Quality->rules($this->max()), 'min:0'];
    }
}
