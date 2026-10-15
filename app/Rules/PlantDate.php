<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\TimezoneService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Una fecha que digita el usuario (un día de calendario, sin hora), validada contra el día de la planta.
 *
 * - Exige `Y-m-d` y que el día exista: `09/10/2026` (que `date` leía como 10 de septiembre), `2026-1-5` y
 *   `2026-02-30` se rechazan. Nunca se acepta una fecha porque PHP «logró interpretarla».
 * - «Hoy» es `TimezoneService::todayInPlant()`, nunca `today()`: después de las 7 p. m. en Bogotá ya es mañana en UTC
 *   (auditoría 3.6, COT-01 y PV-01).
 * - Compara las fechas como texto `Y-m-d`, así que no depende de la zona del proceso PHP. No consulta la base ni cambia
 *   el valor: los límites que dependen de un registro los calcula el request y se los pasa.
 */
final class PlantDate implements ValidationRule
{
    private bool $notFuture = false;

    private bool $notPast = false;

    private ?string $notBefore = null;

    private string $notBeforeReference = '';

    private ?string $allowed = null;

    public static function make(): self
    {
        return new self;
    }

    /** No después de hoy en la planta (movimientos, fecha de cotización). */
    public function notFuture(): self
    {
        $this->notFuture = true;

        return $this;
    }

    /** No antes de hoy en la planta (fecha planificada, fecha requerida, entrega de muestra). */
    public function notPast(): self
    {
        $this->notPast = true;

        return $this;
    }

    /**
     * No antes de un día dado (`Y-m-d`). `$reference` lo nombra en el mensaje: «la fecha del pedido».
     */
    public function notBefore(string $date, string $reference): self
    {
        $this->notBefore = $date;
        $this->notBeforeReference = $reference;

        return $this;
    }

    /**
     * Exime de los límites solo al valor idéntico a la fecha ya guardada, para que un registro viejo se pueda volver a
     * guardar sin cambiarla. Cualquier otra fecha pasa por la regla normal, y el formato se exige siempre.
     */
    public function allowing(?string $savedDate): self
    {
        $this->allowed = $savedDate;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isCalendarDate($value)) {
            $fail('Elige una fecha válida.')->translate();

            return;
        }

        if ($this->allowed !== null && $value === $this->allowed) {
            return;
        }

        $today = app(TimezoneService::class)->todayInPlant();

        if ($this->notFuture && strcmp($value, $today) > 0) {
            $fail('La :attribute no puede ser futura.')->translate();

            return;
        }

        if ($this->notPast && strcmp($value, $today) < 0) {
            $fail('La :attribute no puede ser anterior a hoy.')->translate();

            return;
        }

        if ($this->notBefore !== null && strcmp($value, $this->notBefore) < 0) {
            $fail('La :attribute no puede ser anterior a :reference (:date).')->translate([
                'reference' => $this->notBeforeReference,
                'date' => app(TimezoneService::class)->formatPlantDate($this->notBefore),
            ]);
        }
    }

    private static function isCalendarDate(string $value): bool
    {
        // `\z` y no `$`: `$` también acepta un salto de línea final.
        return preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $value, $parts) === 1
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }
}
