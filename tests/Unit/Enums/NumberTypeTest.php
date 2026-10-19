<?php

declare(strict_types=1);

use App\Casts\WholeUnits;
use App\Enums\NumberType;
use App\Models\ProductionOrder;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

function numberTypePasses(NumberType $type, mixed $value): bool
{
    return Validator::make(['valor' => $value], ['valor' => ['bail', 'required', ...$type->rules()]])->passes();
}

test('cada tipo declara el tamaño de su columna', function (NumberType $type, ?int $precision, int $scale, string $max, string $cast): void {
    expect($type->precision())->toBe($precision)
        ->and($type->scale())->toBe($scale)
        ->and($type->max())->toBe($max)
        ->and($type->cast())->toBe($cast);
})->with([
    'cantidad' => [NumberType::Quantity, 12, 4, '99999999.9999', 'decimal:4'],
    'unidades' => [NumberType::Units, null, 0, '2147483647', WholeUnits::class],
    'dinero' => [NumberType::Money, 16, 4, '999999999999.9999', 'decimal:4'],
    'porcentaje' => [NumberType::Percentage, 5, 2, '999.99', 'decimal:2'],
    'porcentaje calculado' => [NumberType::ComputedPercentage, 12, 4, '99999999.9999', 'decimal:4'],
    'factor' => [NumberType::Factor, 14, 6, '99999999.999999', 'decimal:6'],
    'densidad' => [NumberType::Density, 10, 4, '999999.9999', 'decimal:4'],
    'calidad' => [NumberType::Quality, 4, 1, '999.9', 'decimal:1'],
]);

test('las reglas aceptan lo que cabe en la columna y rechazan lo que no', function (NumberType $type, mixed $fits, mixed $tooManyDecimals, mixed $tooBig): void {
    expect(numberTypePasses($type, $fits))->toBeTrue()
        ->and(numberTypePasses($type, $tooManyDecimals))->toBeFalse()
        ->and(numberTypePasses($type, $tooBig))->toBeFalse();
})->with([
    'cantidad' => [NumberType::Quantity, '99999999.9999', '1.12345', '100000000'],
    'unidades' => [NumberType::Units, '2147483647', '2.5', '2147483648'],
    'dinero' => [NumberType::Money, '120015000.5', '1.12345', '1000000000000'],
    'porcentaje' => [NumberType::Percentage, '999.99', '12.345', '1000'],
    'factor' => [NumberType::Factor, '0.000264', '0.0002641', '100000000'],
    'calidad' => [NumberType::Quality, '999.9', '99.99', '1000'],
]);

test('el cast de unidades convierte el texto de bcmath y se detiene ante una fracción', function (): void {
    $cast = new WholeUnits;
    $model = new class extends Model {};

    expect($cast->set($model, 'quantity', '7.0000', []))->toBe(7)
        ->and($cast->set($model, 'quantity', 3, []))->toBe(3)
        ->and($cast->set($model, 'quantity', null, []))->toBeNull()
        ->and($cast->get($model, 'quantity', '5', []))->toBe(5);

    expect(fn () => $cast->set($model, 'quantity', '2.5', []))->toThrow(DomainException::class);
});

test('el dinero se digita con 2 decimales aunque la columna guarde 4 (P5-4)', function (): void {
    expect(NumberType::Money->inputScale())->toBe(2)
        ->and(NumberType::Money->scale())->toBe(4)
        ->and(NumberType::Money->rules())->toContain('decimal:0,2')
        ->and(NumberType::Quantity->inputScale())->toBe(4)
        ->and(numberTypePasses(NumberType::Money, '1500.5'))->toBeTrue()
        ->and(numberTypePasses(NumberType::Money, '1500.505'))->toBeFalse();
});

test('ensureFits deja pasar el máximo de la columna y rechaza lo que lo pasa con un 422 en el campo', function (NumberType $type, string $fits, string $tooBig): void {
    $type->ensureFits($fits, 'quantity', 'No cabe');

    expect(fn () => $type->ensureFits($tooBig, 'quantity', 'No cabe'))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toBe(['quantity' => ['No cabe']]));
})->with([
    'cantidad' => [NumberType::Quantity, '99999999.9999', '100000000'],
    // PostgreSQL redondea a la escala al guardar: .99994 queda en .9999 y cabe; .99995 sube a 100.000.000.
    'cantidad que se redondea hacia abajo' => [NumberType::Quantity, '99999999.99994', '99999999.99995'],
    'negativa' => [NumberType::Quantity, '-99999999.9999', '-100000000'],
    'unidades' => [NumberType::Units, '2147483647', '2147483648'],
    'dinero' => [NumberType::Money, '999999999999.9999', '1000000000000'],
]);

// El front copia los tamaños en resources/js/lib/number-types.ts para no dejar escribir lo que la columna no guarda.
// Si cambia un tipo aquí y no allá, este test lo detiene.
test('el espejo del front tiene los mismos tamaños que el enum', function (): void {
    $source = (string) file_get_contents(base_path('resources/js/lib/number-types.ts'));
    $mirror = [
        'quantity' => NumberType::Quantity,
        'money' => NumberType::Money,
        'percentage' => NumberType::Percentage,
        'factor' => NumberType::Factor,
        'density' => NumberType::Density,
        'quality' => NumberType::Quality,
    ];

    foreach ($mirror as $key => $type) {
        expect(preg_match("/^\s*{$key}: \{ maxDecimals: (\d+), maxIntegerDigits: (\d+) \}/m", $source, $match))
            ->toBe(1, "Falta «{$key}» en number-types.ts");

        expect((int) $match[1])->toBe($type->inputScale(), "{$key}: decimales que se digitan")
            ->and((int) $match[2])->toBe($type->precision() - $type->scale(), "{$key}: dígitos enteros");
    }

    expect(preg_match('/UNITS_MAX_DIGITS = (\d+);/', $source, $match))->toBe(1)
        ->and((int) $match[1])->toBe(strlen(NumberType::Units->max()));

    // Topes de negocio que el front traduce a dígitos enteros (PR 5).
    foreach (['PRODUCTION_ORDER_QUANTITY_LIMITS' => ProductionOrder::MAX_QUANTITY, 'PRESENTATION_VALUE_LIMITS' => ProductVariant::MAX_PRESENTATION_VALUE] as $name => $max) {
        expect(preg_match("/{$name}: DecimalLimits = \{[^}]*maxIntegerDigits: (\d+),/s", $source, $match))->toBe(1, "Falta {$name}")
            ->and((int) $match[1])->toBe(strlen($max), "{$name}: dígitos enteros");
    }
});

test('el cast de unidades entiende la notación científica y respeta el rango del entero', function (): void {
    $cast = new WholeUnits;
    $model = new class extends Model {};

    expect($cast->set($model, 'quantity', '1.2E+3', []))->toBe(1200);
    expect(fn () => $cast->set($model, 'quantity', '1.0E+20', []))->toThrow(DomainException::class);
    expect(fn () => $cast->set($model, 'quantity', '2147483648', []))->toThrow(DomainException::class);
});
