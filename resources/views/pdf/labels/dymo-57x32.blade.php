{{--
    Estampita de lote para el rollo DYMO 30334 (57×32 mm, térmico directo). Una página por estampita.
    La impresora solo pinta negro o blanco: la jerarquía va con tamaño y peso, nunca con grises (salen punteados).
    Helvetica y no DejaVu: DomPDF no trae DejaVu Condensed y la Helvetica en negrita es ~11 % más angosta, lo que deja
    más nombres en 2 líneas. El nombre llega ya ajustado a 2 líneas con su tamaño (8 pt, o 7 pt si no cabe; DomPDF no
    tiene line-clamp).

    $format: LabelFormat (medidas y margen: una sola fuente con el ajuste del nombre).
    $labels: list<array{name: string, name_size: int, presentation_lines: list<string>, presentation_size: int,
                        lot: int, manufactured_on: string, verify_on: string, qr: string}>
    Lo arma PrintProductionLabelsAction con LabelTextFitterService: `presentation_lines` es «Galón · 12345678» o, si no
    cabe, la presentación y el código en dos líneas; `qr` un data URI PNG y las fechas van formateadas en hora de planta.
--}}
@php
    $margin = $format->marginMm();
    $contentWidth = $format->widthMm() - 2 * $margin;
    // Un poco menos de alto para que nada se desborde a otra página.
    $contentHeight = $format->heightMm() - 2 * $margin - 0.2;
@endphp
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Estampitas</title>
    <style>
        @page {
            margin: 0;
        }

        * {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: {{ \App\Services\LabelTextFitterService::FONT_FAMILY }}, Arial, sans-serif;
            color: #000;
            background: #fff;
        }

        /* El margen va como padding: DomPDF no aplica el margen de @page aquí. */
        .label {
            width: {{ $contentWidth }}mm;
            height: {{ $contentHeight }}mm;
            padding: {{ $margin }}mm;
            overflow: hidden;
        }

        .page-break {
            page-break-after: always;
        }

        .name {
            height: 6.4mm;
            overflow: hidden;
            font-weight: bold;
            line-height: 1.12;
            /* Una palabra más ancha que la línea se parte en vez de salirse por el costado. */
            overflow-wrap: break-word;
        }

        .rule {
            margin: 0.5mm 0 1mm;
            border-top: 0.5pt solid #000;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            padding: 0;
            vertical-align: top;
        }

        .presentation {
            line-height: 1.15;
        }

        /* Cada línea en un solo renglón: si algo no cabe se corta de lado y nunca crece de alto. Un bloque más alto que
           la página lo pasa DomPDF entero a otra (un overflow: hidden de alto fijo no lo evita). */
        .presentation-line {
            width: {{ $format->presentationWidthMm() }}mm;
            white-space: nowrap;
            overflow: hidden;
        }

        /* Espacios de 0,8 mm entre presentación, lote y fechas: con la presentación en dos líneas a 7 pt, 1,3 mm ya no
           cabía y DomPDF pasaba el bloque a otra página. Igual en todas las estampitas, para que se vean iguales. */
        .lot {
            margin-top: 0.8mm;
            font-size: 6pt;
            font-weight: bold;
        }

        .lot-number {
            font-size: 13pt;
        }

        .dates {
            margin-top: 0.8mm;
            font-size: 6.5pt;
            line-height: 1.3;
        }

        .dates td {
            vertical-align: baseline;
        }

        .date-label {
            width: 15mm;
        }

        .verify {
            font-weight: bold;
        }

        .qr-cell {
            width: {{ $format->qrSizeMm() }}mm;
            text-align: right;
        }

        .qr-cell img {
            width: {{ $format->qrSizeMm() }}mm;
            height: {{ $format->qrSizeMm() }}mm;
        }
    </style>
</head>

<body>
    @foreach ($labels as $label)
        <div class="label{{ $loop->last ? '' : ' page-break' }}">
            <div class="name" style="font-size: {{ $label['name_size'] }}pt">{{ $label['name'] }}</div>
            <div class="rule"></div>
            <table>
                <tr>
                    <td>
                        <div class="presentation" style="font-size: {{ $label['presentation_size'] }}pt">
                            @foreach ($label['presentation_lines'] as $line)
                                <div class="presentation-line">{{ $line }}</div>
                            @endforeach
                        </div>
                        <div class="lot">LOTE <span class="lot-number">{{ $label['lot'] }}</span></div>
                        <table class="dates">
                            <tr>
                                <td class="date-label">Fabricación</td>
                                <td>{{ $label['manufactured_on'] }}</td>
                            </tr>
                            <tr class="verify">
                                <td class="date-label">Verificación</td>
                                <td>{{ $label['verify_on'] }}</td>
                            </tr>
                        </table>
                    </td>
                    <td class="qr-cell">
                        <img src="{{ $label['qr'] }}" alt="QR">
                    </td>
                </tr>
            </table>
        </div>
    @endforeach
</body>

</html>
