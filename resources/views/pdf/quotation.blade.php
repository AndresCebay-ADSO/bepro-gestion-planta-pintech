<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotización - {{ $quotation['quotation_number'] }}</title>
    <style>
        @page {
            margin: 18px 18px 14px 18px;
        }

        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 9px;
            color: #222;
            line-height: 1.25;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .no-border td,
        .no-border th {
            border: none;
        }

        .top-table td {
            vertical-align: middle;
        }

        .logo {
            max-width: 135px;
            max-height: 70px;
        }

        .quotation-no {
            font-size: 18px;
            font-weight: bold;
            text-align: right;
            color: #333;
            padding-top: 10px;
        }

        .brand-bar {
            margin-top: 8px;
            margin-bottom: 4px;
            height: 28px;
            background: #2f67b1;
            color: #fff;
            font-size: 13px;
            font-weight: bold;
            text-align: right;
            padding: 5px 18px 0 0;
            border-bottom: 4px solid #e91e84;
        }

        .section-title {
            background: #575757;
            color: #fff;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            font-size: 8px;
            padding: 4px 0;
        }

        .info-wrap td {
            vertical-align: top;
            border: 1px solid #bdbdbd;
            width: 33.33%;
        }

        .info-table td {
            border-bottom: 1px solid #e1e1e1;
            padding: 3px 5px;
            font-size: 8px;
        }

        .info-label {
            width: 42%;
            font-weight: bold;
            background: #f5f5f5;
        }

        .info-value {
            width: 58%;
        }

        .items-table {
            margin-top: 6px;
        }

        .items-table th {
            background: #575757;
            color: #fff;
            font-size: 7.5px;
            padding: 4px 3px;
            border: 1px solid #4a4a4a;
            text-align: center;
            font-weight: bold;
        }

        .items-table td {
            border: 1px solid #c9c9c9;
            padding: 2px 3px;
            font-size: 8px;
            vertical-align: middle;
        }

        .gray-row td {
            background: #f4f4f4;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }

        .totals-label {
            font-weight: bold;
            text-align: right;
            background: #f5f5f5;
            padding-right: 6px;
        }

        .totals-value {
            font-weight: normal;
            text-align: right;
            background: #f5f5f5;
            padding-right: 6px;
        }

        .notes-title {
            margin-top: 12px;
            color: #2f67b1;
            font-weight: bold;
            font-size: 9px;
            margin-bottom: 5px;
        }

        .notes-text {
            font-size: 8px;
            margin-bottom: 4px;
            text-align: justify;
        }

        .advisor-block {
            margin-top: 16px;
            font-size: 8.5px;
        }

        .advisor-name {
            font-weight: bold;
            font-size: 10px;
            margin-top: 18px;
        }

        .footer {
            margin-top: 16px;
            width: 100%;
        }

        .footer-left {
            width: 62%;
            background: #2f67b1;
            color: #fff;
            vertical-align: top;
            padding: 10px 12px;
            border-top: 4px solid #e91e84;
        }

        .footer-right {
            width: 38%;
            text-align: center;
            vertical-align: middle;
            padding-top: 8px;
        }

        .footer-offices {
            width: 100%;
        }

        .footer-offices td {
            vertical-align: top;
            width: 50%;
            padding-right: 10px;
            font-size: 8px;
            line-height: 1.35;
        }

        .footer-office-title {
            font-weight: bold;
            margin-bottom: 2px;
            font-size: 9px;
        }

        .footer-logo {
            max-width: 125px;
            max-height: 55px;
            margin-top: 18px;
        }
    </style>
</head>
<body>

    <table class="top-table no-border">
        <tr>
            <td style="width: 55%;">
                @if ($beproLogoBase64)
                    <img src="{{ $beproLogoBase64 }}" alt="BePro Coatings" class="logo">
                @else
                    <div style="font-size: 20px; font-weight: bold;">BePro COATINGS</div>
                @endif
            </td>
            <td style="width: 45%;">
                <div class="quotation-no">Cotización No.{{ $quotation['quotation_number'] }}</div>
            </td>
        </tr>
    </table>

    <div class="brand-bar">{{ config('quotation.brand.website') }}</div>

    <table class="info-wrap">
        <tr>
            <td>
                <div class="section-title">Cliente</div>
                <table class="info-table no-border">
                    <tr>
                        <td class="info-label">Cliente</td>
                        <td class="info-value">{{ $quotation['client']['business_name'] ?? 'N.D.' }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">NIT</td>
                        <td class="info-value">{{ $quotation['client']['nit'] ?? 'N.D.' }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Contacto</td>
                        <td class="info-value">{{ $quotation['client']['contact_name'] ?? 'N.D.' }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Teléfono</td>
                        <td class="info-value">{{ $quotation['client']['phone'] ?? 'N.D.' }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">M2</td>
                        <td class="info-value">{{ !empty($quotation['area']) ? $quotation['area'].' m2' : 'N.D.' }}</td>
                    </tr>
                </table>
            </td>

            <td>
                <div class="section-title">Sistema ofertado</div>
                <table class="info-table no-border">
                    <tr>
                        <td class="info-label">Tecnología</td>
                        <td class="info-value">{{ $quotation['technology'] ?? 'N.D.' }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Línea</td>
                        <td class="info-value">{{ $quotation['line'] ?? 'N.D.' }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Espesor en Mils</td>
                        <td class="info-value">{{ !empty($quotation['thickness_mils']) ? $quotation['thickness_mils'].' Mils' : 'N.D.' }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Método de aplicación</td>
                        <td class="info-value">{{ $quotation['application_method'] ?? 'N.A.' }}</td>
                    </tr>
                </table>
            </td>

            <td>
                <div class="section-title">Condiciones comerciales</div>
                <table class="info-table no-border">
                    <tr>
                        <td class="info-label">Fecha</td>
                        <td class="info-value">{{ $quotation['quotation_date'] ?? 'N.D.' }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Validez</td>
                        <td class="info-value">{{ !empty($quotation['validity_days']) ? $quotation['validity_days'] : 'N.D.' }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Forma de Pago</td>
                        <td class="info-value">{{ $quotation['payment_method'] ?? 'N.D.' }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Tiempo de entrega</td>
                        <td class="info-value">{{ $quotation['delivery_time'] ?? 'N.D.' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 4%;">Item</th>
                <th style="width: 10%;">Tipo</th>
                <th style="width: 16%;">Producto / Referencia</th>
                <th style="width: 20%;">Descripción comercial</th>
                <th style="width: 10%;">Color</th>
                <th style="width: 10%;">Presentación</th>
                <th style="width: 7%;">Cantidad</th>
                <th style="width: 11%;">Precio Unitario</th>
                <th style="width: 12%;">Precio Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quotation['items'] as $index => $item)
                <tr class="{{ $index % 2 ? 'gray-row' : '' }}">
                    <td class="text-center">{{ $item['sort_order'] ?? ($index + 1) }}</td>
                    <td class="text-center">{{ $item['item_type'] ?? '' }}</td>
                    <td class="text-left">{{ $item['product_reference'] ?? '' }}</td>
                    <td class="text-left">{{ $item['description'] ?? $item['product_reference'] ?? '' }}</td>
                    <td class="text-center">{{ $item['color'] ?? '' }}</td>
                    <td class="text-center">{{ $item['presentation_label'] ?? '' }}</td>
                    <td class="text-center">{{ number_format($item['quantity'] ?? 0, 0, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($item['unit_price'] ?? 0, 0, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($item['subtotal'] ?? 0, 0, ',', '.') }}</td>
                </tr>
            @endforeach

            <tr>
                <td colspan="7" style="border:none;"></td>
                <td class="totals-label">Subtotal</td>
                <td class="totals-value">${{ number_format($quotation['subtotal'] ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td colspan="7" style="border:none;"></td>
                <td class="totals-label">IVA {{ number_format($quotation['iva_percentage'] ?? 0, 0) }}%</td>
                <td class="totals-value">${{ number_format($quotation['iva_amount'] ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td colspan="7" style="border:none;"></td>
                <td class="totals-label">Total</td>
                <td class="totals-value">${{ number_format($quotation['total'] ?? 0, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="notes-title">Notas y alcance:</div>

    @foreach (config('quotation.legal_notes', []) as $note)
        <div class="notes-text">- {{ $note }}</div>
    @endforeach

    @if (!empty($quotation['notes']))
        <div class="notes-text">- {{ $quotation['notes'] }}</div>
    @endif

    <div class="advisor-block">
        <div class="advisor-name">Asesor:</div>
        <div>{{ $quotation['advisor']['name'] ?? 'N.D.' }}</div>
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

    <table class="footer no-border">
        <tr>
            <td class="footer-left">
                <table class="footer-offices no-border">
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
            </td>
            <td class="footer-right">
                @if ($pintechLogoBase64)
                    <img src="{{ $pintechLogoBase64 }}" alt="Pintech" class="footer-logo">
                @else
                    <div style="font-size: 20px; font-weight: bold; color: #444;">Pintech</div>
                @endif
            </td>
        </tr>
    </table>

</body>
</html>