<?php

declare(strict_types=1);

use App\Enums\LabelFormat;
use App\Services\LabelTextFitterService;
use Dompdf\Dompdf;

beforeEach(function () {
    $this->fitter = new LabelTextFitterService;
    $this->width = LabelFormat::Dymo57x32->contentWidthPt();
    $this->presentationWidth = LabelFormat::Dymo57x32->presentationWidthPt();
    $this->metrics = (new Dompdf)->getFontMetrics();
});

test('un nombre corto va a 8 pt sin recortar', function () {
    expect($this->fitter->fitName('VINILO TIPO 1 BLANCO', null, $this->width, $this->metrics))
        ->toBe(['name' => 'VINILO TIPO 1 BLANCO', 'size' => 8]);
});

test('lleva el color de la orden pegado al nombre', function () {
    expect($this->fitter->fitName('ESMALTE SINTÉTICO', 'RAL 3020', $this->width, $this->metrics)['name'])
        ->toBe('ESMALTE SINTÉTICO RAL 3020');
});

test('baja a 7 pt antes de recortar si así cabe en dos líneas', function () {
    // El nombre más largo de desarrollo con un color: a 8 pt pide 3 líneas y perdería «1/4 AP-301».
    $fitted = $this->fitter->fitName('BP POLIESTER ALUMINIO LENTICULAR EXTRA FINO 1/4 AP-301', 'RAL 3020', $this->width, $this->metrics);

    expect($fitted)->toBe([
        'name' => 'BP POLIESTER ALUMINIO LENTICULAR EXTRA FINO 1/4 AP-301 RAL 3020',
        'size' => 7,
    ]);
});

test('si ni a 7 pt cabe, recorta el nombre del producto y nunca el color', function () {
    $fitted = $this->fitter->fitName('BP POLIESTER ALUMINIO LENTICULAR EXTRA FINO 1/4 AP-301', 'AZUL CORPORATIVO CLIENTE', $this->width, $this->metrics);

    expect($fitted['size'])->toBe(7)
        ->and($fitted['name'])->toStartWith('BP POLIESTER ALUMINIO LENTICULAR')
        ->and($fitted['name'])->toEndWith('… AZUL CORPORATIVO CLIENTE');
});

test('un color de más de 25 caracteres también se recorta', function () {
    $fitted = $this->fitter->fitName('ESMALTE', 'AZUL CORPORATIVO DEL CLIENTE PRINCIPAL', $this->width, $this->metrics);

    expect($fitted['name'])->toBe('ESMALTE AZUL CORPORATIVO DEL CLIE…');
});

test('parte después de un guion, como DomPDF, y no recorta de más', function () {
    // Sin el corte en el guion, «LENTICULAR-EXTRA-FINO-1/4-AP-301» contaría como una palabra que no cabe.
    $fitted = $this->fitter->fitName('BP POLIESTER ALUMINIO LENTICULAR-EXTRA-FINO-1/4-AP-301', 'RAL 3020', $this->width, $this->metrics);

    expect($fitted)->toBe([
        'name' => 'BP POLIESTER ALUMINIO LENTICULAR-EXTRA-FINO-1/4-AP-301 RAL 3020',
        'size' => 7,
    ]);
});

test('una palabra más ancha que la línea cuenta las líneas que ocupa partida', function () {
    $fitted = $this->fitter->fitName(str_repeat('M', 60), null, $this->width, $this->metrics);

    expect($fitted['size'])->toBe(7)
        ->and($fitted['name'])->toEndWith('…');
});

// Presentación y código, a la izquierda del QR.

test('la presentación y un código corto van en una línea a 7 pt', function () {
    expect($this->fitter->fitPresentation('Galón', '12345678', $this->presentationWidth, $this->metrics))
        ->toBe(['lines' => ['Galón · 12345678'], 'size' => 7]);
});

test('si no caben en una línea, van en dos a 7 pt sin recortar nada', function () {
    // El caso real: un código de 16 dígitos partía la línea y pasaba la estampita a una segunda página.
    expect($this->fitter->fitPresentation('Tambor 50G', '7707477132250891', $this->presentationWidth, $this->metrics))
        ->toBe(['lines' => ['Tambor 50G', '7707477132250891'], 'size' => 7]);
});

test('baja a 6 pt cuando el código solo cabe así en su línea', function () {
    $code = str_repeat('7', 26);

    expect($this->fitter->fitPresentation('Galón', $code, $this->presentationWidth, $this->metrics))
        ->toBe(['lines' => ['Galón', $code], 'size' => 6]);
});

test('recorta la presentación y deja el código entero', function () {
    $fitted = $this->fitter->fitPresentation(str_repeat('P', 50), '7707477132250891', $this->presentationWidth, $this->metrics);

    expect($fitted['size'])->toBe(6)
        ->and($fitted['lines'][0])->toEndWith('…')
        ->and($fitted['lines'][1])->toBe('7707477132250891');
});

test('un código que ni solo en su línea cabe se recorta con puntos suspensivos', function () {
    $fitted = $this->fitter->fitPresentation('Galón', str_repeat('7', 80), $this->presentationWidth, $this->metrics);

    expect($fitted['size'])->toBe(6)
        ->and($fitted['lines'][0])->toBe('Galón')
        ->and($fitted['lines'][1])->toStartWith('7777')->toEndWith('…');
});

test('sin presentación va solo el código', function () {
    expect($this->fitter->fitPresentation(null, '12345678', $this->presentationWidth, $this->metrics))
        ->toBe(['lines' => ['12345678'], 'size' => 7])
        ->and($this->fitter->fitPresentation('', str_repeat('7', 80), $this->presentationWidth, $this->metrics)['lines'][0])
        ->toEndWith('…');
});
