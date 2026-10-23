<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tono de color de un estado (DESIGN.md §4). Son cinco y no hay más: el front traduce cada tono a clases en un solo
 * sitio (`resources/js/lib/tones.ts`). Cada enum que se pinta declara el suyo con `HasTone::tone()`.
 */
enum Tone: string
{
    /** Borrador, inactivo, consumido, tipos y categorías. */
    case Neutral = 'neutral';

    /** En progreso, enviado, traslado. */
    case Info = 'info';

    /** Pendiente, en revisión, parcial, salida. */
    case Warning = 'warning';

    /** Lista, entregada, completada, aprobada, aceptada, disponible, activo, entrada. */
    case Success = 'success';

    /** Rechazada, cancelada, alerta alta, prioridad alta. */
    case Danger = 'danger';
}
