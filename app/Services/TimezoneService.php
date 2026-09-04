<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

class TimezoneService
{
    public function __construct(
        private readonly string $plantTimezone = 'America/Bogota',
    ) {}

    public function getPlantTimezone(): string
    {
        return $this->plantTimezone;
    }

    /**
     * Convierte una fecha/timestamp a la zona horaria de planta.
     */
    public function toPlantTime(CarbonInterface|string|null $date): ?CarbonInterface
    {
        if ($date === null) {
            return null;
        }

        if (is_string($date)) {
            $trimmed = trim($date);

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
                return Date::parse($trimmed, $this->plantTimezone)->startOfDay();
            }

            $parsed = Date::parse($trimmed);

            return $parsed->setTimezone($this->plantTimezone);
        }

        return $date->setTimezone($this->plantTimezone);
    }

    /**
     * Formatea fecha y hora en la zona horaria de planta para PDFs y reportes.
     */
    public function formatPlantDateTime(CarbonInterface|string|null $date, string $format = 'd/m/Y H:i'): string
    {
        $plantDate = $this->toPlantTime($date);

        if ($plantDate === null) {
            return '';
        }

        return $plantDate->format($format);
    }

    /**
     * Formatea solo la fecha (día) en la zona horaria de planta.
     */
    public function formatPlantDate(CarbonInterface|string|null $date, string $format = 'd/m/Y'): string
    {
        return $this->formatPlantDateTime($date, $format);
    }

    /**
     * Retorna la fecha/hora actual en la zona de planta.
     */
    public function nowInPlant(): CarbonInterface
    {
        return Date::now($this->plantTimezone);
    }
}
