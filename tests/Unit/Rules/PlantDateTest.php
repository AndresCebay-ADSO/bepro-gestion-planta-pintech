<?php

declare(strict_types=1);

use App\Rules\PlantDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

// 01:30 UTC del 14-oct = 20:30 del 13-oct en Bogotá: «hoy» en planta es 13-oct y en UTC ya es 14-oct.
beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-14 01:30:00', 'UTC'));
});

/**
 * @return list<string>
 */
function plantDateErrors(mixed $value, PlantDate $rule): array
{
    return Validator::make(['fecha_x' => $value], ['fecha_x' => [$rule]], [], ['fecha_x' => 'fecha de prueba'])
        ->errors()
        ->get('fecha_x');
}

it('accepts a real Y-m-d date', function (string $value): void {
    expect(plantDateErrors($value, PlantDate::make()))->toBe([]);
})->with(['2026-10-13', '2024-02-29', '0001-01-01', '9999-12-31']);

it('rejects anything that is not a real Y-m-d date without throwing', function (mixed $value): void {
    expect(plantDateErrors($value, PlantDate::make()))->toBe(['Elige una fecha válida.']);
})->with([
    'formato de EE. UU.' => '09/10/2026',
    'sin ceros' => '2026-1-5',
    'día que no existe' => '2026-02-30',
    'mes 13' => '2026-13-01',
    'año no bisiesto' => '2026-02-29',
    'año de cinco dígitos' => '10000-01-01',
    'con hora' => '2026-10-13 10:00:00',
    'espacios' => ' 2026-10-13',
    'salto de línea final' => "2026-10-13\n",
    'texto' => 'mañana',
    'arreglo' => [['2026-10-13']],
    'número' => 20261013,
    'booleano' => true,
]);

it('notFuture accepts today in the plant and rejects the UTC today', function (): void {
    $rule = fn () => PlantDate::make()->notFuture();

    expect(plantDateErrors('2026-10-13', $rule()))->toBe([])
        ->and(plantDateErrors('2026-01-01', $rule()))->toBe([])
        ->and(plantDateErrors('2026-10-14', $rule()))->toBe(['La fecha de prueba no puede ser futura.']);
});

it('notPast accepts today in the plant and rejects yesterday', function (): void {
    $rule = fn () => PlantDate::make()->notPast();

    expect(plantDateErrors('2026-10-13', $rule()))->toBe([])
        ->and(plantDateErrors('2099-12-31', $rule()))->toBe([])
        ->and(plantDateErrors('2026-10-12', $rule()))->toBe(['La fecha de prueba no puede ser anterior a hoy.']);
});

it('notBefore names the reference and its date in the message', function (): void {
    $rule = fn () => PlantDate::make()->notBefore('2026-10-10', 'la fecha del pedido');

    expect(plantDateErrors('2026-10-10', $rule()))->toBe([])
        ->and(plantDateErrors('2026-10-09', $rule()))
        ->toBe(['La fecha de prueba no puede ser anterior a la fecha del pedido (10/10/2026).']);
});

it('allowing only exempts the exact saved date', function (): void {
    $rule = fn () => PlantDate::make()->notPast()->allowing('2026-09-01');

    expect(plantDateErrors('2026-09-01', $rule()))->toBe([])
        ->and(plantDateErrors('2026-09-02', $rule()))->toBe(['La fecha de prueba no puede ser anterior a hoy.'])
        ->and(plantDateErrors('2026-10-13', $rule()))->toBe([])
        ->and(plantDateErrors('2026-11-01', $rule()))->toBe([]);
});

it('allowing a null saved date exempts nothing', function (): void {
    expect(plantDateErrors('2026-09-01', PlantDate::make()->notPast()->allowing(null)))
        ->toBe(['La fecha de prueba no puede ser anterior a hoy.']);
});

it('allowing still requires a real date', function (): void {
    expect(plantDateErrors('2026-02-30', PlantDate::make()->notPast()->allowing('2026-02-30')))
        ->toBe(['Elige una fecha válida.']);
});
