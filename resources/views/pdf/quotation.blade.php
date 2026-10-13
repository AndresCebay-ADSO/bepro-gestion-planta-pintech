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
            '<svg xmlns="http://www.w3.org/2000/svg" width="530" height="100" viewBox="0 0 530 100">'
            .'<polygon points="0,0 530,0 525,6 0,6" fill="'.$brandPink.'"/>'
            .'<polygon points="0,6 525,6 460,100 0,100" fill="'.$brandBlue.'"/>'
            .'</svg>'
        );

        $money = fn ($value): string => '$'.number_format((float) ($value ?? 0), 0, ',', '.');

        $sideBySide = ($closingLayout ?? null) === \App\Actions\Quotations\RenderQuotationPdfAction::LAYOUT_SIDE_BY_SIDE;
    @endphp
    <style>
        /*
         * Carta vertical. El margen inferior reserva el pie de sedes, que es fijo y se repite en cada hoja.
         * No usar un reinicio `* { margin: 0 }`: en DomPDF también alcanza a la página y anula estos márgenes.
         */
        @page {
            margin: 32pt 40pt 90pt 40pt;
        }

        body {
            margin: 0;
            font-family: Helvetica, Arial, sans-serif;
            font-size: 8.5pt;
            color: #222;
            line-height: 1.2;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td,
        th {
            padding: 0;
            vertical-align: top;
        }

        /* ── Encabezado ── */
        .header-logo {
            height: 58pt;
        }

        .quotation-no {
            font-size: 12pt;
            font-weight: bold;
            color: #333;
            text-align: right;
            padding-right: 14pt;
            margin-bottom: 6pt;
        }

        .band {
            position: relative;
            height: 32pt;
        }

        .band img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 32pt;
        }

        .band-website {
            position: absolute;
            top: 7pt;
            right: 14pt;
            color: #fff;
            font-size: 13pt;
            font-weight: bold;
        }

        /* ── Cliente / Condiciones comerciales / Sistema ofertado ── */
        .info {
            margin-top: 10pt;
        }

        .info-block th {
            background: #595959;
            color: #fff;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            padding: 1.5pt 0;
            border-bottom: 1.5pt solid #000;
        }

        .info-block td {
            padding: 1pt 3pt;
        }

        .info-block .label {
            font-weight: bold;
            white-space: nowrap;
        }

        .stripe td {
            background: #f2f2f2;
        }

        .system {
            margin-top: 6pt;
        }

        .system td {
            width: 25%;
            padding: 1pt 3pt;
        }

        .system .label {
            font-weight: bold;
            background: #f2f2f2;
        }

        /* ── Ítems ── */
        .items-table {
            margin-top: 8pt;
            font-size: 8pt;
        }

        .items-table th {
            background: #595959;
            color: #fff;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            padding: 3pt 2pt;
            border-bottom: 1.5pt solid #000;
        }

        .items-table td {
            padding: 1.5pt 3pt;
            text-align: center;
            vertical-align: middle;
        }

        .items-table td.product {
            text-align: left;
        }

        .product-name {
            font-weight: bold;
        }

        .product-description {
            font-size: 7.5pt;
            color: #555;
        }

        .items-table td.amount {
            text-align: right;
            white-space: nowrap;
        }

        .totals-first td.totals {
            border-top: 1.5pt solid #000;
        }

        .items-table td.totals {
            text-align: right;
            padding-top: 1.5pt;
            white-space: nowrap;
        }

        .items-table .grand-total td.totals {
            font-weight: bold;
            font-size: 9.5pt;
        }

        /* ── Notas y asesor: uno debajo del otro, o lado a lado si no caben así ── */
        .closing {
            margin-top: 6pt;
            page-break-inside: avoid;
        }

        .notes-title {
            margin-bottom: 3pt;
            color: {{ $brandBlue }};
            font-weight: bold;
        }

        .observations {
            margin-top: 8pt;
            line-height: 1.3;
        }

        /* Notas legales: letra pequeña, como en un contrato. */
        .notes-table td {
            padding-bottom: 1.5pt;
            font-size: 6.5pt;
            color: #666;
            line-height: 1.3;
            text-align: justify;
        }

        .notes-table td.dash {
            width: 9pt;
        }

        .closing-side .advisor {
            padding-left: 18pt;
        }

        .closing-stacked .advisor {
            width: 200pt;
            margin-top: 12pt;
        }

        .advisor-label {
            font-size: 8pt;
            font-weight: bold;
        }

        .advisor-signature {
            height: 34pt;
            margin-top: 2pt;
        }

        .advisor-signature img {
            max-height: 34pt;
            max-width: 160pt;
        }

        .advisor-name {
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 1pt;
            border-top: 0.75pt solid #999;
            padding-top: 2pt;
        }

        .advisor-detail {
            line-height: 1.25;
        }

        /* ── Pie fijo: sedes y logo Pintech ── */
        .footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: -70pt;
            height: 62pt;
        }

        .footer-band {
            position: relative;
            height: 62pt;
        }

        .footer-band img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 62pt;
        }

        .footer-offices {
            position: absolute;
            top: 10pt;
            left: 14pt;
            width: 290pt;
            color: #fff;
        }

        .footer-offices td {
            width: 50%;
            font-size: 8pt;
            line-height: 1.2;
        }

        .footer-office-title {
            font-weight: bold;
        }

        .footer-logo {
            text-align: center;
            vertical-align: middle;
        }

        .footer-logo img {
            width: 110pt;
        }
    </style>
</head>
<body>

    {{-- ⑦ PIE FIJO: SEDES Y LOGO PINTECH (va primero para que DomPDF lo repita en cada hoja) --}}
    <table class="footer">
        <tr>
            <td style="width: 66%;">
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
            <td class="footer-logo" style="width: 34%;">
                @if ($pintechLogoBase64)
                    <img src="{{ $pintechLogoBase64 }}" alt="Pintech">
                @else
                    <div style="font-size: 18pt; font-weight: bold; color: #444;">Pintech</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- ① ENCABEZADO --}}
    <table>
        <tr>
            <td style="width: 30%;">
                @if ($beproLogoBase64)
                    <img src="{{ $beproLogoBase64 }}" alt="BePro Coatings" class="header-logo">
                @else
                    <div style="font-size: 18pt; font-weight: bold;">BePro COATINGS</div>
                @endif
            </td>
            <td style="width: 70%; vertical-align: bottom;">
                <div class="quotation-no">Cotización No.{{ $quotation['quotation_number'] }}</div>
                <div class="band">
                    <img src="{{ $headerBand }}" alt="">
                    <div class="band-website">{{ config('quotation.brand.website') }}</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- ② CLIENTE / CONDICIONES COMERCIALES --}}
    <table class="info">
        <tr>
            <td style="width: 54%; padding-right: 6pt;">
                <table class="info-block">
                    <tr><th colspan="2">Cliente</th></tr>
                    <tr>
                        <td class="label" style="width: 22%;">Cliente</td>
                        <td>{{ $quotation['client']['business_name'] ?? 'N.D.' }}</td>
                    </tr>
                    <tr class="stripe">
                        <td class="label">NIT</td>
                        <td>{{ $quotation['client']['nit'] ?? 'N.D.' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Contacto</td>
                        <td>{{ $quotation['client']['contact_name'] ?? 'N.D.' }}</td>
                    </tr>
                    <tr class="stripe">
                        <td class="label">Teléfono</td>
                        <td>{{ $quotation['client']['phone'] ?? 'N.D.' }}</td>
                    </tr>
                    <tr>
                        <td class="label">M2</td>
                        <td>{{ $quotation['area'] }}</td>
                    </tr>
                </table>
            </td>
            <td style="width: 46%;">
                <table class="info-block">
                    <tr><th colspan="2">Condiciones comerciales</th></tr>
                    <tr>
                        <td class="label" style="width: 42%;">Fecha</td>
                        <td>{{ $quotation['quotation_date'] ?? 'N.D.' }}</td>
                    </tr>
                    <tr class="stripe">
                        <td class="label">Validez</td>
                        <td>{{ $quotation['validity_days'] ?? 'N.D.' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Forma de Pago</td>
                        <td>{{ $quotation['payment_method'] ?? 'N.D.' }}</td>
                    </tr>
                    <tr class="stripe">
                        <td class="label">Tiempo de entrega</td>
                        <td>{{ $quotation['delivery_time'] ?? 'N.D.' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ③ SISTEMA OFERTADO --}}
    <table class="info-block system">
        <tr><th colspan="4">Sistema ofertado</th></tr>
        <tr>
            <td class="label">Tecnología</td>
            <td class="label">Línea</td>
            <td class="label">Espesor en Mils</td>
            <td class="label">Método de aplicación</td>
        </tr>
        <tr>
            <td>{{ $quotation['technology'] ?? 'N.D.' }}</td>
            <td>{{ $quotation['line'] ?? 'N.D.' }}</td>
            <td>{{ $quotation['thickness_mils'] }}</td>
            <td>{{ $quotation['application_method'] ?? 'N.A.' }}</td>
        </tr>
    </table>

    {{-- ④ ÍTEMS Y TOTALES --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 4%;">Ítem</th>
                <th style="width: 11%;">Tipo</th>
                <th style="width: 36%;">Producto / Descripción</th>
                <th style="width: 11%;">Color</th>
                <th style="width: 10%;">Presentación</th>
                <th style="width: 6%;">Cant.</th>
                <th style="width: 10%;">Precio Unitario</th>
                <th style="width: 12%;">Precio Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quotation['items'] as $index => $item)
                <tr class="{{ $index % 2 ? 'stripe' : '' }}">
                    <td>{{ $item['sort_order'] ?? ($index + 1) }}</td>
                    <td>{{ $item['item_type'] ?? '' }}</td>
                    <td class="product">
                        <div class="product-name">{{ $item['product_reference'] ?? '' }}</div>
                        @if (filled($item['description'] ?? null) && $item['description'] !== ($item['product_reference'] ?? null))
                            <div class="product-description">{{ $item['description'] }}</div>
                        @endif
                    </td>
                    <td>{{ $item['color'] ?? '' }}</td>
                    <td>{{ $item['presentation_label'] ?? '' }}</td>
                    <td>{{ $item['quantity'] }}</td>
                    <td class="amount">{{ $money($item['unit_price'] ?? 0) }}</td>
                    <td class="amount">{{ $money($item['subtotal'] ?? 0) }}</td>
                </tr>
            @endforeach

            <tr class="totals-first">
                <td colspan="6"></td>
                <td class="totals">Subtotal</td>
                <td class="totals">{{ $money($quotation['subtotal']) }}</td>
            </tr>
            <tr>
                <td colspan="6"></td>
                <td class="totals">IVA {{ number_format((float) ($quotation['iva_percentage'] ?? 0), 0) }}%</td>
                <td class="totals">{{ $money($quotation['iva_amount']) }}</td>
            </tr>
            <tr class="grand-total">
                <td colspan="6"></td>
                <td class="totals">Total</td>
                <td class="totals">{{ $money($quotation['total']) }}</td>
            </tr>
        </tbody>
    </table>

    {{--
        ⑤ OBSERVACIONES DEL COMERCIAL. Van fuera del bloque de cierre: ese bloque no se parte entre hojas, y un texto
        largo dentro de él lo empujaría entero a la hoja siguiente dejando la primera a medio llenar.
    --}}
    @if (filled($quotation['notes'] ?? null))
        <div class="observations">
            <div class="notes-title">Observaciones:</div>
            <div>{!! nl2br(e($quotation['notes'])) !!}</div>
        </div>
    @endif

    {{--
        ⑥ NOTAS Y ASESOR. Por defecto el asesor firma debajo de las notas. RenderQuotationPdfAction los pone lado a
        lado solo cuando así la firma se pasaría a otra hoja.
    --}}
    @if ($sideBySide)
        <table class="closing closing-side">
            <tr>
                <td style="width: 64%;">@include('pdf.partials.quotation-notes')</td>
                <td class="advisor" style="width: 36%;">@include('pdf.partials.quotation-advisor')</td>
            </tr>
        </table>
    @else
        <div class="closing closing-stacked">
            @include('pdf.partials.quotation-notes')
            <div class="advisor">@include('pdf.partials.quotation-advisor')</div>
        </div>
    @endif

</body>
</html>
