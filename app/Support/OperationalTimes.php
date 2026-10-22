<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Las horas de agitación y envasado que la planta anota en la OP: cómo se leen y qué se exige de ellas (auditoría 3.6,
 * OPL-07). Son instantes, no días de calendario: se comparan como instantes, así que una fase que cruza la medianoche
 * es válida mientras el fin sea posterior al inicio.
 *
 * Una hora con zona (`…Z`, `…-05:00`) es un instante exacto; sin zona, es hora de planta. Lo que se exige no depende del
 * formato del campo en pantalla: si el campo cambia (una hora sola y una fecha de producción), solo cambia lo que se envía.
 */
final class OperationalTimes
{
    /** Ninguna fase de la OP dura más de dos días. */
    public const MAX_PHASE_HOURS = 48;

    /** Margen para un reloj del navegador adelantado. */
    public const FUTURE_TOLERANCE_MINUTES = 5;

    /** @var array<string, array{0: string, 1: string, 2: string}> fase => [campo de inicio, campo de fin, nombre en el mensaje] */
    private const PHASES = [
        'agitation' => ['agitation_start_time', 'agitation_end_time', 'La agitación'],
        'packaging' => ['packaging_start_time', 'packaging_end_time', 'El envasado'],
    ];

    /**
     * El instante en UTC, o `null` si el texto está vacío o no es una fecha y hora completas. Acepta `2026-10-13T08:00`,
     * con segundos y fracciones, con `T` o espacio, y con `Z` o desfase.
     */
    public static function parse(mixed $value): ?CarbonImmutable
    {
        // Un instante que ya viene como fecha (una llamada desde un job, un seeder o un test) se respeta: devolver `null`
        // borraría la hora sin avisar.
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->utc();
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (preg_match('/\A\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2}(\.\d{1,6})?)?(Z|[+-]\d{2}:?\d{2})?\z/i', $value) !== 1) {
            return null;
        }

        // `parse` corrige un 31 de febrero al 3 de marzo: el día que se leyó debe ser el que se escribió.
        [$year, $month, $day] = array_map('intval', explode('-', substr($value, 0, 10)));

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        $hasZone = preg_match('/(Z|[+-]\d{2}:?\d{2})\z/i', $value) === 1;

        try {
            $parsed = $hasZone
                ? CarbonImmutable::parse($value)
                : CarbonImmutable::parse($value, (string) config('app.plant_timezone'));
        } catch (\Throwable) {
            return null;
        }

        return $parsed->utc();
    }

    /**
     * Los problemas de las cuatro horas, por campo. Un campo vacío no tiene problema: la hora es opcional.
     *
     * @param  array<string, mixed>  $input
     * @param  list<string>  $skipFields  campos que ya tienen error por otra regla
     * @return array<string, string> campo => mensaje en español
     */
    public static function problems(array $input, array $skipFields = []): array
    {
        $problems = [];
        $limit = CarbonImmutable::now()->addMinutes(self::FUTURE_TOLERANCE_MINUTES);

        foreach (self::PHASES as [$startField, $endField, $phaseLabel]) {
            $instants = [];

            foreach ([$startField, $endField] as $field) {
                $raw = $input[$field] ?? null;

                if (in_array($field, $skipFields, true) || ! is_string($raw) || trim($raw) === '') {
                    continue;
                }

                $instant = self::parse($raw);

                if ($instant === null) {
                    $problems[$field] = __('Elige una fecha y hora válidas.');

                    continue;
                }

                if ($instant->greaterThan($limit)) {
                    $problems[$field] = __('La :attribute no puede ser futura.', ['attribute' => __("validation.attributes.{$field}")]);

                    continue;
                }

                $instants[$field] = $instant;
            }

            if (! isset($instants[$startField], $instants[$endField])) {
                continue;
            }

            if ($instants[$endField]->lessThan($instants[$startField])) {
                $problems[$endField] = __('La :attribute no puede ser anterior a la de inicio.', ['attribute' => __("validation.attributes.{$endField}")]);
            } elseif ($instants[$startField]->diffInMinutes($instants[$endField], absolute: true) > self::MAX_PHASE_HOURS * 60) {
                $problems[$endField] = __(':phase no puede durar más de :hours horas.', ['phase' => $phaseLabel, 'hours' => self::MAX_PHASE_HOURS]);
            }
        }

        return $problems;
    }
}
