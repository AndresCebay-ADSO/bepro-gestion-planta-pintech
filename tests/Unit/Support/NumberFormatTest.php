<?php

declare(strict_types=1);

use App\Support\NumberFormat;

describe('NumberFormat: tipos de número aprobados para Pintech OS', function () {
    it('formatea cantidades (quantity): hasta 4 decimales, sin ceros finales', function (mixed $value, string $expected) {
        expect(NumberFormat::quantity($value))->toBe($expected);
    })->with([
        ['12.5000', '12,5'],
        ['0.0375', '0,0375'],
        ['250.0000', '250'],
        [250, '250'],
        ['0', '0'],
        ['-12.5000', '-12,5'],
    ]);

    it('formatea unidades enteras (units): entero sin decimales con punto de miles', function (mixed $value, string $expected) {
        expect(NumberFormat::units($value))->toBe($expected);
    })->with([
        [48, '48'],
        ['48', '48'],
        ['1250', '1.250'],
        [1250000, '1.250.000'],
        [0, '0'],
        [-15, '-15'],
    ]);

    it('formatea precio al cliente (clientPrice): pesos enteros con signo de pesos', function (mixed $value, string $expected) {
        expect(NumberFormat::clientPrice($value))->toBe($expected);
    })->with([
        [1250000, '$1.250.000'],
        ['1250000', '$1.250.000'],
        ['1250000.40', '$1.250.000'],
        ['1250000.50', '$1.250.001'],
        [0, '$0'],
        [-50000, '-$50.000'],
    ]);

    it('formatea costo interno (cost): signo de pesos, hasta 2 decimales, sin ceros finales', function (mixed $value, string $expected) {
        expect(NumberFormat::cost($value))->toBe($expected);
    })->with([
        ['12345.50', '$12.345,5'],
        [12345.5, '$12.345,5'],
        ['12345.00', '$12.345'],
        ['12345.567', '$12.345,57'],
        [0, '$0'],
        ['-12345.50', '-$12.345,5'],
    ]);

    it('formatea costo unitario de materia prima (unitCost): signo de pesos, hasta 4 decimales, sin ceros finales', function (mixed $value, string $expected) {
        expect(NumberFormat::unitCost($value))->toBe($expected);
    })->with([
        ['4250.1234', '$4.250,1234'],
        ['4250.1000', '$4.250,1'],
        ['4250.0000', '$4.250'],
        [0, '$0'],
        ['-4250.1234', '-$4.250,1234'],
    ]);

    it('formatea porcentaje (percent): puntos porcentuales, hasta 2 decimales, sin ceros finales y pegado', function (mixed $value, string $expected) {
        expect(NumberFormat::percent($value))->toBe($expected);
    })->with([
        [19, '19%'],
        ['19', '19%'],
        ['19.50', '19,5%'],
        [19.5, '19,5%'],
        ['19.555', '19,56%'],
        [0, '0%'],
        ['-5.2', '-5,2%'],
    ]);

    it('no pierde dígitos en números de 16 cifras (sin floats)', function () {
        expect(NumberFormat::cost('1234567890123456.7890'))->toBe('$1.234.567.890.123.456,79');
        expect(NumberFormat::clientPrice('1234567890123456'))->toBe('$1.234.567.890.123.456');
        expect(NumberFormat::quantity('1234567890123456.7890'))->toBe('1.234.567.890.123.456,789');
    });

    it('devuelve «—» por defecto para null, vacíos o no numéricos, y respeta emptyValue personalizado', function () {
        expect(NumberFormat::quantity(null))->toBe('—');
        expect(NumberFormat::clientPrice(''))->toBe('—');
        expect(NumberFormat::cost('   '))->toBe('—');
        expect(NumberFormat::percent('abc'))->toBe('—');

        expect(NumberFormat::clientPrice(null, 'Sin precio'))->toBe('Sin precio');
        expect(NumberFormat::cost(null, 'Sin costo'))->toBe('Sin costo');
        expect(NumberFormat::quantity(null, 'N.D.'))->toBe('N.D.');
    });

    it('formatea mediciones (measurement) con los decimales de su columna', function () {
        // Viscosidad y Hegman son numeric(4,1): conservan su decimal.
        expect(NumberFormat::measurement('85.0', 1))->toBe('85,0');
        expect(NumberFormat::measurement('7.5', 1))->toBe('7,5');
        expect(NumberFormat::measurement('1234.56', 2))->toBe('1.234,56');
        expect(NumberFormat::measurement(null, 1))->toBe('—');
    });
});
