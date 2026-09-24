<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotización - {{ $quotation['quotation_number'] }}</title>
    @php
        // Bandas diagonales del encabezado y del pie. DomPDF no recorta divs en diagonal, así que se dibujan en SVG.
        $brandBlue = '#2f67b1';
        $brandPink = '#e91e84';
        $svgUri = fn (string $svg): string => 'data:image/svg+xml;base64,'.base64_encode($svg);

        $headerBand = $svgUri(
            '<svg xmlns="http://www.w3.org/2000/svg" width="840" height="76" viewBox="0 0 840 76">'
            .'<polygon points="108,0 840,0 840,67 10,67" fill="'.$brandBlue.'"/>'
            .'<polygon points="10,67 840,67 840,76 0,76" fill="'.$brandPink.'"/>'
            .'</svg>'
        );

        $footerBand = $svgUri(
            '<svg xmlns="http://www.w3.org/2000/svg" width="810" height="118" viewBox="0 0 810 118">'
            .'<polygon points="0,0 810,0 804,8 0,8" fill="'.$brandPink.'"/>'
            .'<polygon points="0,8 804,8 722,118 0,118" fill="'.$brandBlue.'"/>'
            .'</svg>'
        );

        $money = fn ($value): string => '$'.number_format((float) ($value ?? 0), 0, ',', '.');
    @endphp
    <style>
        @page {
            margin: 26pt 36pt 22pt 36pt;
        }

        * {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 9.5pt;
            color: #222;
            line-height: 1.15;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        /* ── Encabezado ── */
        .header-logo {
            height: 124pt;
            margin-left: 40pt;
        }

        .quotation-no {
            font-size: 13pt;
            font-weight: bold;
            color: #333;
            text-align: right;
            padding-right: 50pt;
            margin-bottom: 8pt;
        }

        .band {
            position: relative;
            height: 41pt;
        }

        .band img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 41pt;
        }

        .band-website {
            position: absolute;
            top: 9pt;
            right: 18pt;
            color: #fff;
            font-size: 17pt;
            font-weight: bold;
        }

        /* ── Cliente / Sistema ofertado / Condiciones comerciales ── */
        .info-table {
            margin-top: 12pt;
        }

        .info-table th {
            background: #595959;
            color: #fff;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            padding: 1.5pt 0;
            border-bottom: 1.5pt solid #000;
        }

        .info-table td {
            padding: 1pt 3pt;
            white-space: nowrap;
        }

        .info-table .label {
            font-weight: bold;
        }

        .stripe td {
            background: #f2f2f2;
        }

        /* ── Ítems ── */
        .items-table {
            margin-top: 4pt;
        }

        .items-table th {
            background: #595959;
            color: #fff;
            font-weight: bold;
            text-align: center;
            padding: 4pt 2pt;
            border-bottom: 1.5pt solid #000;
        }

        .items-table th.small {
            font-size: 8.5pt;
        }

        .items-table td {
            padding: 1pt 3pt;
            text-align: center;
        }

        .items-table td.amount {
            text-align: right;
        }

        .totals-first td.totals {
            border-top: 1.5pt solid #000;
        }

        .items-table td.totals {
            text-align: right;
            padding-top: 1.5pt;
        }

        .items-table .grand-total td.totals {
            font-weight: bold;
        }

        /* ── Notas ── */
        .notes-title {
            margin-top: 12pt;
            margin-bottom: 4pt;
            padding-left: 12pt;
            color: {{ $brandBlue }};
            font-weight: bold;
        }

        .notes-table {
            width: 88%;
        }

        .notes-table td {
            vertical-align: top;
            padding-bottom: 3pt;
            line-height: 1.3;
        }

        .notes-table td.dash {
            width: 12pt;
            padding-left: 4pt;
        }

        /* ── Asesor y pie ── */
        .closing {
            page-break-inside: avoid;
        }

        .advisor {
            margin-top: 18pt;
            padding-left: 10pt;
        }

        .advisor-label {
            font-size: 8pt;
            font-weight: bold;
        }

        .advisor-signature {
            height: 40pt;
            margin-top: 4pt;
        }

        .advisor-signature img {
            max-height: 40pt;
            max-width: 190pt;
        }

        .advisor-name {
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 2pt;
        }

        .advisor-detail {
            font-size: 8.5pt;
            line-height: 1.35;
        }

        .footer {
            margin-top: 12pt;
        }

        .footer-band {
            position: relative;
            height: 64pt;
        }

        .footer-band img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 64pt;
        }

        .footer-offices {
            position: absolute;
            top: 12pt;
            left: 22pt;
            width: 390pt;
            color: #fff;
        }

        .footer-offices td {
            width: 50%;
            vertical-align: top;
            font-size: 10pt;
            line-height: 1.15;
        }

        .footer-office-title {
            font-weight: bold;
        }

        .footer-logo {
            text-align: center;
            vertical-align: middle;
        }

        .footer-logo img {
            width: 130pt;
        }
    </style>
</head>
<body>

    {{-- ① ENCABEZADO --}}
    <table>
        <tr>
            <td style="width: 35%; vertical-align: top;">
                @if ($beproLogoBase64)
                    <img src="{{ $beproLogoBase64 }}" alt="BePro Coatings" class="header-logo">
                @else
                    <div style="font-size: 20pt; font-weight: bold;">BePro COATINGS</div>
                @endif
            </td>
            <td style="width: 65%; vertical-align: bottom;">
                <div class="quotation-no">Cotización No.{{ $quotation['quotation_number'] }}</div>
                <div class="band">
                    <img src="{{ $headerBand }}" alt="">
                    <div class="band-website">{{ config('quotation.brand.website') }}</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- ② CLIENTE / SISTEMA OFERTADO / CONDICIONES COMERCIALES --}}
    <table class="info-table">
        <tr>
            <th colspan="2" style="width: 31.5%;">Cliente</th>
            <th colspan="2" style="width: 31%;">Sistema ofertado</th>
            <th colspan="2" style="width: 37.5%;">Condiciones comerciales</th>
        </tr>
        <tr>
            <td class="label" style="width: 7%;">Cliente</td>
            <td style="width: 24.5%;">{{ $quotation['client']['business_name'] ?? 'N.D.' }}</td>
            <td class="label" style="width: 18%;">Tecnología</td>
            <td style="width: 13%;">{{ $quotation['technology'] ?? 'N.D.' }}</td>
            <td class="label" style="width: 13.5%;">Fecha</td>
            <td style="width: 24%;">{{ $quotation['quotation_date'] ?? 'N.D.' }}</td>
        </tr>
        <tr class="stripe">
            <td class="label">NIT</td>
            <td>{{ $quotation['client']['nit'] ?? 'N.D.' }}</td>
            <td class="label">Línea</td>
            <td>{{ $quotation['line'] ?? 'N.D.' }}</td>
            <td class="label">Validez</td>
            <td>{{ $quotation['validity_days'] ?? 'N.D.' }}</td>
        </tr>
        <tr>
            <td class="label">Contacto</td>
            <td>{{ $quotation['client']['contact_name'] ?? 'N.D.' }}</td>
            <td class="label">Espesor en Mils</td>
            <td>{{ $quotation['thickness_mils'] }}</td>
            <td class="label">Forma de Pago</td>
            <td>{{ $quotation['payment_method'] ?? 'N.D.' }}</td>
        </tr>
        <tr class="stripe">
            <td class="label">Teléfono</td>
            <td>{{ $quotation['client']['phone'] ?? 'N.D.' }}</td>
            <td class="label">Método de aplicación</td>
            <td>{{ $quotation['application_method'] ?? 'N.A.' }}</td>
            <td class="label">Tiempo de entrega</td>
            <td>{{ $quotation['delivery_time'] ?? 'N.D.' }}</td>
        </tr>
        <tr>
            <td class="label">M2</td>
            <td>{{ $quotation['area'] }}</td>
            <td colspan="4"></td>
        </tr>
    </table>

    {{-- ③ ÍTEMS Y TOTALES --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 6.5%;">Ítem</th>
                <th style="width: 10%;">Tipo</th>
                <th style="width: 16%;">Producto / Referencia</th>
                <th style="width: 16%;">Descripción comercial</th>
                <th style="width: 13.5%;">Color</th>
                <th style="width: 11.5%;">Presentación</th>
                <th style="width: 8.5%;">Cantidad</th>
                <th class="small" style="width: 9%;">Precio Unitario</th>
                <th class="small" style="width: 9%;">Precio Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quotation['items'] as $index => $item)
                <tr class="{{ $index % 2 ? 'stripe' : '' }}">
                    <td>{{ $item['sort_order'] ?? ($index + 1) }}</td>
                    <td>{{ $item['item_type'] ?? '' }}</td>
                    <td>{{ $item['product_reference'] ?? '' }}</td>
                    <td>{{ $item['description'] ?? $item['product_reference'] ?? '' }}</td>
                    <td>{{ $item['color'] ?? '' }}</td>
                    <td>{{ $item['presentation_label'] ?? '' }}</td>
                    <td>{{ $item['quantity'] }}</td>
                    <td class="amount">{{ $money($item['unit_price'] ?? 0) }}</td>
                    <td class="amount">{{ $money($item['subtotal'] ?? 0) }}</td>
                </tr>
            @endforeach

            <tr class="totals-first">
                <td colspan="7"></td>
                <td class="totals">Subtotal</td>
                <td class="totals">{{ $money($quotation['subtotal']) }}</td>
            </tr>
            <tr>
                <td colspan="7"></td>
                <td class="totals">IVA {{ number_format((float) ($quotation['iva_percentage'] ?? 0), 0) }}%</td>
                <td class="totals">{{ $money($quotation['iva_amount']) }}</td>
            </tr>
            <tr class="grand-total">
                <td colspan="7"></td>
                <td class="totals">Total</td>
                <td class="totals">{{ $money($quotation['total']) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- ④ NOTAS Y ALCANCE --}}
    <div class="notes-title">Notas y alcance:</div>
    <table class="notes-table">
        @foreach (config('quotation.legal_notes', []) as $note)
            <tr>
                <td class="dash">-</td>
                <td>{{ $note }}</td>
            </tr>
        @endforeach
        @if (!empty($quotation['notes']))
            <tr>
                <td class="dash">-</td>
                <td>{{ $quotation['notes'] }}</td>
            </tr>
        @endif
    </table>

    <div class="closing">
        {{-- ⑤ ASESOR --}}
        <div class="advisor">
            <div class="advisor-label">Asesor:</div>
            <div class="advisor-signature">
                @if (!empty($quotation['advisor']['signature']))
                    <img src="{{ $quotation['advisor']['signature'] }}" alt="Firma del asesor">
                @endif
            </div>
            <div class="advisor-name">{{ $quotation['advisor']['name'] ?? 'N.D.' }}</div>
            <div class="advisor-detail">
                @if (!empty($quotation['advisor']['job_title']))
                    <div>{{ $quotation['advisor']['job_title'] }}</div>
                @endif
                @if (!empty($quotation['advisor']['phone']))
                    <div>Móvil: {{ $quotation['advisor']['phone'] }}</div>
                @endif
                @if (!empty($quotation['advisor']['email']))
                    <div>Email: {{ $quotation['advisor']['email'] }}</div>
                @endif
            </div>
        </div>

        {{-- ⑥ PIE: SEDES Y LOGO PINTECH --}}
        <table class="footer">
            <tr>
                <td style="width: 63%;">
                    <div class="footer-band">
                        <img src="{{ $footerBand }}" alt="">
                        <table class="footer-offices">
                            <tr>
                                @foreach (config('quotation.footer_offices', []) as $office)
                                    <td>
                                        <div class="footer-office-title">{{ $office['label'] }}</div>
                                        <div>{{ implode(' / ', $office['phones'] ?? []) }}</div>
                                        <div>{{ $office['address'] ?? '' }}</div>
                                        <div>{{ $office['city'] ?? '' }}</div>
                                    </td>
                                @endforeach
                            </tr>
                        </table>
                    </div>
                </td>
                <td class="footer-logo" style="width: 37%;">
                    @if ($pintechLogoBase64)
                        <img src="{{ $pintechLogoBase64 }}" alt="Pintech">
                    @else
                        <div style="font-size: 20pt; font-weight: bold; color: #444;">Pintech</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
