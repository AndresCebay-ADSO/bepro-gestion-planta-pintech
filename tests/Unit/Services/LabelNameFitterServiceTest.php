<?php

declare(strict_types=1);

use App\Enums\LabelFormat;
use App\Services\LabelNameFitterService;
use Dompdf\Dompdf;

beforeEach(function () {
    $this->fitter = new LabelNameFitterService;
    $this->width = LabelFormat::Dymo57x32->contentWidthPt();
    $this->metrics = (new Dompdf)->getFontMetrics();
});

test('un nombre corto va a 8 pt sin recortar', function () {
    expect($this->fitter->fit('VINILO TIPO 1 BLANCO', null, $this->width, $this->metrics))
        ->toBe(['name' => 'VINILO TIPO 1 BLANCO', 'size' => 8]);
});

test('lleva el color de la orden pegado al nombre', function () {
    expect($this->fitter->fit('ESMALTE SINTÉTICO', 'RAL 3020', $this->width, $this->metrics)['name'])
        ->toBe('ESMALTE SINTÉTICO RAL 3020');
});

test('baja a 7 pt antes de recortar si así cabe en dos líneas', function () {
    // El nombre más largo de desarrollo con un color: a 8 pt pide 3 líneas y perdería «1/4 AP-301».
    $fitted = $this->fitter->fit('BP POLIESTER ALUMINIO LENTICULAR EXTRA FINO 1/4 AP-301', 'RAL 3020', $this->width, $this->metrics);

    expect($fitted)->toBe([
        'name' => 'BP POLIESTER ALUMINIO LENTICULAR EXTRA FINO 1/4 AP-301 RAL 3020',
        'size' => 7,
    ]);
});

test('si ni a 7 pt cabe, recorta el nombre del producto y nunca el color', function () {
    $fitted = $this->fitter->fit('BP POLIESTER ALUMINIO LENTICULAR EXTRA FINO 1/4 AP-301', 'AZUL CORPORATIVO CLIENTE', $this->width, $this->metrics);

    expect($fitted['size'])->toBe(7)
        ->and($fitted['name'])->toStartWith('BP POLIESTER ALUMINIO LENTICULAR')
        ->and($fitted['name'])->toEndWith('… AZUL CORPORATIVO CLIENTE');
});

test('un color de más de 25 caracteres también se recorta', function () {
    $fitted = $this->fitter->fit('ESMALTE', 'AZUL CORPORATIVO DEL CLIENTE PRINCIPAL', $this->width, $this->metrics);

    expect($fitted['name'])->toBe('ESMALTE AZUL CORPORATIVO DEL CLIE…');
});

test('parte después de un guion, como DomPDF, y no recorta de más', function () {
    // Sin el corte en el guion, «LENTICULAR-EXTRA-FINO-1/4-AP-301» contaría como una palabra que no cabe.
    $fitted = $this->fitter->fit('BP POLIESTER ALUMINIO LENTICULAR-EXTRA-FINO-1/4-AP-301', 'RAL 3020', $this->width, $this->metrics);

    expect($fitted)->toBe([
        'name' => 'BP POLIESTER ALUMINIO LENTICULAR-EXTRA-FINO-1/4-AP-301 RAL 3020',
        'size' => 7,
    ]);
});

test('una palabra más ancha que la línea cuenta las líneas que ocupa partida', function () {
    $fitted = $this->fitter->fit(str_repeat('M', 60), null, $this->width, $this->metrics);

    expect($fitted['size'])->toBe(7)
        ->and($fitted['name'])->toEndWith('…');
});
