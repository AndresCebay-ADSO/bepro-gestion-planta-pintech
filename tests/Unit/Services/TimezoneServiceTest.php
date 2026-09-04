<?php

declare(strict_types=1);

use App\Services\TimezoneService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->service = new TimezoneService('America/Bogota');
});

test('retorna la zona horaria de planta configurada', function () {
    expect($this->service->getPlantTimezone())->toBe('America/Bogota');
});

test('convierte un Carbon UTC a la hora oficial de planta', function () {
    // 20:00 UTC corresponde a las 15:00 en Colombia (UTC-5)
    $utcDate = CarbonImmutable::parse('2026-09-03 20:00:00', 'UTC');
    $plantDate = $this->service->toPlantTime($utcDate);

    expect($plantDate)->not->toBeNull();
    expect($plantDate?->timezoneName)->toBe('America/Bogota');
    expect($plantDate?->format('Y-m-d H:i:s'))->toBe('2026-09-03 15:00:00');
});

test('convierte un string UTC a la hora oficial de planta', function () {
    $plantDate = $this->service->toPlantTime('2026-09-04 01:30:00');

    expect($plantDate)->not->toBeNull();
    expect($plantDate?->timezoneName)->toBe('America/Bogota');
    // 01:30 UTC del 4 de septiembre son las 20:30 del 3 de septiembre en Colombia
    expect($plantDate?->format('Y-m-d H:i:s'))->toBe('2026-09-03 20:30:00');
});

test('convierte un string de solo fecha YYYY-MM-DD sin desfase al dia anterior', function () {
    $plantDate = $this->service->toPlantTime('2026-09-03');

    expect($plantDate)->not->toBeNull();
    expect($plantDate?->timezoneName)->toBe('America/Bogota');
    expect($plantDate?->format('Y-m-d'))->toBe('2026-09-03');
    expect($this->service->formatPlantDate('2026-09-03'))->toBe('03/09/2026');
});

test('retorna null cuando la fecha es null', function () {
    expect($this->service->toPlantTime(null))->toBeNull();
    expect($this->service->formatPlantDateTime(null))->toBe('');
    expect($this->service->formatPlantDate(null))->toBe('');
});

test('formatea fecha y hora en zona de planta para PDFs', function () {
    $utcDate = CarbonImmutable::parse('2026-09-04 02:15:00', 'UTC');
    $formatted = $this->service->formatPlantDateTime($utcDate);

    // 02:15 UTC del 4 de septiembre son las 21:15 del 3 de septiembre en Colombia
    expect($formatted)->toBe('03/09/2026 21:15');
});

test('formatea solo fecha en zona de planta', function () {
    $utcDate = CarbonImmutable::parse('2026-09-04 01:00:00', 'UTC');
    $formatted = $this->service->formatPlantDate($utcDate);

    expect($formatted)->toBe('03/09/2026');
});

test('nowInPlant retorna la hora en la zona de planta', function () {
    $nowInPlant = $this->service->nowInPlant();

    expect($nowInPlant->timezoneName)->toBe('America/Bogota');
});
