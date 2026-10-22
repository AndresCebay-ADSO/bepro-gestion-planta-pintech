<?php

declare(strict_types=1);

use App\Support\OperationalTimes;
use Carbon\CarbonImmutable;

/*
 * Auditoría 3.6, PR 10 (OPL-07): cómo se lee una hora de la OP. Lo que se exige de ellas (orden, futuro, duración) se
 * prueba por el endpoint en ProductionOrderCompletionLinesTest.
 */

it('reads an hour with a zone as that exact instant and one without as plant time', function (): void {
    expect(OperationalTimes::parse('2026-10-13T13:00:00Z')?->format('Y-m-d H:i'))->toBe('2026-10-13 13:00')
        ->and(OperationalTimes::parse('2026-10-13T08:00:00-05:00')?->format('Y-m-d H:i'))->toBe('2026-10-13 13:00')
        ->and(OperationalTimes::parse('2026-10-13T08:00')?->format('Y-m-d H:i'))->toBe('2026-10-13 13:00')
        ->and(OperationalTimes::parse('2026-10-13 08:00:30.250')?->format('Y-m-d H:i:s'))->toBe('2026-10-13 13:00:30');
});

it('keeps an instant that already arrives as a date object instead of dropping it', function (): void {
    $plant = CarbonImmutable::parse('2026-10-13 08:00:00', 'America/Bogota');

    expect(OperationalTimes::parse($plant)?->format('Y-m-d H:i'))->toBe('2026-10-13 13:00')
        ->and(OperationalTimes::parse(new DateTime('2026-10-13 13:00:00', new DateTimeZone('UTC')))?->format('Y-m-d H:i'))->toBe('2026-10-13 13:00');
});

it('returns null for an empty value and for text that is not a full date and time', function (mixed $value): void {
    expect(OperationalTimes::parse($value))->toBeNull();
})->with([null, '', '   ', 'mañana', '2026-10-13', '2026-02-30T08:00', '2026-10-13T25:00', '2026-10-13T24:00', '2026-10-13T24:30:00Z', '2026-10-13T23:60', '2026-10-13T23:59:60', 'array' => [['2026-10-13T08:00']], 12]);

it('keeps the last valid second of the day', function (): void {
    expect(OperationalTimes::parse('2026-10-13T23:59:59Z')?->format('Y-m-d H:i:s'))->toBe('2026-10-13 23:59:59')
        ->and(OperationalTimes::parse('2026-10-13T00:00Z')?->format('Y-m-d H:i'))->toBe('2026-10-13 00:00');
});
