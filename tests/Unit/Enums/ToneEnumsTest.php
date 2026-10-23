<?php

declare(strict_types=1);

use App\Enums\Contracts\HasTone;
use App\Enums\SalesOrderStatus;
use App\Enums\Tone;
use App\Support\ToneEnums;
use Tests\TestCase;

uses(TestCase::class);

/*
 * La prop compartida `enums` es la única fuente del color de los estados (DESIGN.md §4). Estas pruebas evitan que un
 * enum con tono quede fuera de la lista o que un caso nuevo llegue sin tono.
 */

/**
 * Enums de `app/Enums` con método `tone()`, leídos del disco para no depender de la lista que se quiere vigilar.
 *
 * @return list<class-string>
 */
function enumsWithTone(): array
{
    $enums = [];

    foreach (glob(app_path('Enums/*.php')) ?: [] as $file) {
        $class = 'App\\Enums\\'.basename($file, '.php');

        if (enum_exists($class) && method_exists($class, 'tone')) {
            $enums[] = $class;
        }
    }

    sort($enums);

    return $enums;
}

test('cada enum de la lista implementa HasTone y da etiqueta y tono a cada caso', function (string $enum): void {
    expect(is_subclass_of($enum, HasTone::class))->toBeTrue("{$enum} no implementa HasTone");

    foreach ($enum::cases() as $case) {
        expect($case->tone())->toBeInstanceOf(Tone::class)
            ->and($case->label())->toBeString()->not->toBe('');
    }
})->with(ToneEnums::ALL);

test('todo enum con tone() está registrado en ToneEnums::ALL', function (): void {
    $registered = ToneEnums::ALL;
    sort($registered);

    expect($registered)->toBe(enumsWithTone());
});

test('la lista no repite el nombre corto de ningún enum (es la clave de la prop)', function (): void {
    $names = array_map(fn (string $enum): string => class_basename($enum), ToneEnums::ALL);

    expect(array_unique($names))->toHaveCount(count($names));
});

test('shared() da la etiqueta y el tono de cada caso por nombre de enum y valor', function (): void {
    $shared = ToneEnums::shared();

    expect($shared)->toHaveCount(count(ToneEnums::ALL))
        ->and($shared['SalesOrderStatus'])->toHaveCount(count(SalesOrderStatus::cases()))
        ->and($shared['SalesOrderStatus']['pending'])->toBe(['label' => 'Pendiente', 'tone' => 'warning'])
        ->and($shared['ProductionOrderStatus']['in_progress'])->toBe(['label' => 'En progreso', 'tone' => 'info'])
        ->and($shared['AlertSeverity']['alta'])->toBe(['label' => 'Alta', 'tone' => 'danger']);
});
