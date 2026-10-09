{{--
    Estampita de lote para el rollo DYMO 30334 (57×32 mm, térmico directo). Una página por estampita.
    La impresora solo pinta negro o blanco: la jerarquía va con tamaño y peso, nunca con grises (salen punteados).
    Helvetica y no DejaVu: DomPDF no trae DejaVu Condensed y la Helvetica en negrita es ~11 % más angosta, lo que deja
    más nombres en 2 líneas. El nombre llega ya ajustado a 2 líneas con su tamaño (8 pt, o 7 pt si no cabe; DomPDF no
    tiene line-clamp).

    $labels: list<array{name: string, name_size: int, presentation: string, lot: int,
                        manufactured_on: string, verify_on: string, qr: string}>
    Lo arma PrintProductionLabelsAction: `presentation` es «Galón · 12345678», `qr` un data URI PNG y las fechas van
    formateadas en hora de planta.
--}}
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
            font-family: Helvetica, Arial, sans-serif;
            color: #000;
            background: #fff;
        }

        /* 2 mm de margen por lado como padding: DomPDF no aplica el margen de @page aquí. Zona útil 53×28 mm, un poco
           menos de alto para que nada se desborde a otra página. */
        .label {
            width: 53mm;
            height: 27.8mm;
            padding: 2mm;
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
            font-size: 7pt;
        }

        .lot {
            margin-top: 1.3mm;
            font-size: 6pt;
            font-weight: bold;
        }

        .lot-number {
            font-size: 13pt;
        }

        .dates {
            margin-top: 1.3mm;
            font-size: 6.5pt;
            line-height: 1.35;
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
            width: 18mm;
            text-align: right;
        }

        .qr-cell img {
            width: 18mm;
            height: 18mm;
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
                        <div class="presentation">{{ $label['presentation'] }}</div>
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
