<?php

declare(strict_types=1);

use App\Casts\NormalizedText;
use App\Enums\TextFormat;
use App\Models\Client;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

function textFormatPasses(TextFormat $format, mixed $value): bool
{
    return Validator::make(['valor' => $value], ['valor' => ['bail', 'required', 'string', ...$format->rules()]])->passes();
}

test('normaliza espacios, tildes y mayúsculas según el formato', function (TextFormat $format, string $input, string $expected): void {
    expect($format->normalize($input))->toBe($expected);
})->with([
    'código en minúsculas' => [TextFormat::Code, 'rm-100', 'RM-100'],
    'código con espacio interno doble' => [TextFormat::Code, 'RM  100', 'RM 100'],
    'código con ñ' => [TextFormat::Code, 'env-m-cuñ', 'ENV-M-CUÑ'],
    'código con tab y espacio duro' => [TextFormat::Code, "rm\t\u{00A0}100", 'RM 100'],
    'nombre de producto en minúsculas' => [TextFormat::ProductName, 'bp esmalte  blanco', 'BP ESMALTE BLANCO'],
    // «E» + acento suelto (como llega al copiar de un PDF) queda como una sola «É».
    'nombre de producto con tilde partida' => [TextFormat::ProductName, "BP ESMALTE POLIME\u{0301}RICO", 'BP ESMALTE POLIMÉRICO'],
    'nombre conserva las mayúsculas' => [TextFormat::Name, '  Planta   Cali ', 'Planta Cali'],
    'nombre con tilde partida' => [TextFormat::Name, "Epo\u{0301}xicos", 'Epóxicos'],
    'NIT como texto' => [TextFormat::Name, '901.674.872-1', '901.674.872-1'],
    'código de unidad en minúsculas' => [TextFormat::UnitCode, ' KG ', 'kg'],
]);

test('lo que no es texto llega tal cual, para que lo rechace la regla string', function (mixed $value): void {
    expect(TextFormat::Code->normalize($value))->toBe($value);
})->with([
    'arreglo' => [['RM-1']],
    'número' => [100],
    'nulo' => [null],
]);

test('los códigos siguen la regla de caracteres de D-RM1', function (string $code, bool $passes): void {
    expect(textFormatPasses(TextFormat::Code, $code))->toBe($passes);
})->with([
    'guion' => ['RM-100', true],
    'espacio' => ['BENZ. S', true],
    'barra' => ['ENV-M-1/16', true],
    'Ñ' => ['ENV-M-CUÑ', true],
    'guion bajo' => ['RM_100', true],
    'empieza por guion' => ['-RM', false],
    'emoji' => ['RM/😀', false],
    'barra invertida' => ['RM\\100', false],
    'comillas' => ['RM"100', false],
    'minúsculas sin normalizar' => ['rm-100', false],
]);

test('los nombres de producto admiten los signos de D-PRD2', function (string $name, bool $passes): void {
    expect(textFormatPasses(TextFormat::ProductName, $name))->toBe($passes);
})->with([
    'tildes y Ñ' => ['BP VINILBEPRO + TIEMPO 5 AÑOS', true],
    'porcentaje' => ['BP PRIMER EPÓXICO 60% SÓLIDOS', true],
    'barra' => ['CAOBA / CHOCOLATE', true],
    'fracción' => ['EXTRA FINO 1/4', true],
    'paréntesis, coma y y comercial' => ['CAUCHO & LÁTEX (BASE AGUA), TIPO 1', true],
    'grados' => ['ESMALTE RESISTENTE 200 °C', true],
    'diéresis' => ['PINGÜINO', true],
    'barra invertida' => ['EXTRA\\FINO', false],
    'emoji' => ['ESMALTE 😀', false],
    'comillas' => ['ESMALTE "PREMIUM"', false],
    'numeral' => ['ESMALTE #5', false],
    'marca registrada' => ['ESMALTE®', false],
    'empieza por signo' => ['/ESMALTE', false],
]);

test('los nombres y el código de unidad no tienen regla de caracteres', function (TextFormat $format): void {
    expect($format->rules())->toBe([]);
})->with([TextFormat::Name, TextFormat::UnitCode]);

test('el cast del modelo normaliza lo que no pasa por un formulario', function (): void {
    $rawMaterial = new RawMaterial;
    $rawMaterial->code = ' rm  100 ';

    $product = new Product;
    $product->name = "bp esmalte polime\u{0301}rico";

    $warehouse = new Warehouse;
    $warehouse->name = 'Planta   Cali';

    $unit = new UnitOfMeasure;
    $unit->code = 'KG';

    expect($rawMaterial->code)->toBe('RM 100')
        ->and($unit->code)->toBe('kg')
        ->and($product->name)->toBe('BP ESMALTE POLIMÉRICO')
        ->and($warehouse->name)->toBe('Planta Cali')
        ->and(TextFormat::Code->cast())->toBe(NormalizedText::class.':code');
});

test('el cast guarda como null un texto que queda vacío', function (string $value): void {
    $client = new Client;
    $client->nit = $value;

    expect($client->nit)->toBeNull();
})->with(['vacío' => [''], 'solo espacios' => ['   ']]);
