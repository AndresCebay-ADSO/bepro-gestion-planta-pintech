<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Certificado de Calidad - {{ $certificate['lot'] }}</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #000;
            background: #fff;
            line-height: 1.5;
        }

        .page {
            padding: 52px 64px;
            max-width: 800px;
            margin: 0 auto;
        }

        /* ── HEADER ── */
        .header-table {
            width: 100%;
            margin-bottom: 36px;
            border-collapse: collapse;
            border: none;
        }

        .header-table td {
            vertical-align: top;
            border: none;
            padding: 0;
        }

        .header-left {
            font-size: 12px;
            line-height: 1.6;
            text-align: left;
            width: 50%;
        }

        .header-left .company-name {
            font-weight: bold;
            font-size: 13px;
        }

        .header-left a {
            color: #0462C1;
            text-decoration: underline;
        }

        .header-right {
            text-align: right;
            width: 50%;
        }

        .header-right img {
            max-width: 300px;
            max-height: 220px;
        }

        .header-right .fallback-logo {
            font-size: 22px;
            font-weight: bold;
            letter-spacing: 1px;
            text-align: right;
        }

        /* ── TITLE ── */
        .title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 32px;
        }

        /* ── INFO FIELDS ── */
        .fields {
            margin-bottom: 28px;
        }

        .field {
            margin-bottom: 10px;
            font-size: 13px;
        }

        .field strong {
            font-weight: bold;
        }

        /* ── TESTS TABLE ── */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 28px;
            font-size: 12px;
        }

        table th {
            font-weight: bold;
            text-align: center;
            padding: 8px 10px;
            border: 1px solid #000;
            background: #fff;
        }

        table td {
            padding: 7px 10px;
            border: 1px solid #000;
            text-align: center;
        }

        table tr.row-stripe {
            background-color: #d3d3d3;
        }

        table td:first-child {
            text-align: left;
        }

        table th:first-child {
            text-align: left;
        }

        /* ── DATES ── */
        .dates {
            margin-bottom: 40px;
            font-size: 13px;
            line-height: 1.8;
        }

        .dates strong {
            font-weight: bold;
        }

        /* ── SIGNATURE ── */
        .signature {
            margin-top: 16px;
        }

        .signature .label {
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 16px;
        }

        .signature img {
            display: block;
            max-height: 64px;
            max-width: 200px;
            margin-bottom: 4px;
        }

        .signature .name {
            font-size: 12px;
        }

        .signature .role {
            font-size: 12px;
        }
    </style>
</head>

<body>
    <div class="page">

        {{-- ── HEADER ── --}}
        <table class="header-table">
            <tr>
                <td class="header-left">
                    <div class="company-name">Pintech Colombia SAS</div>
                    <div>NIT. 901123507-9</div>
                    <div>Palmira – Valle del Cauca</div>
                    <div><a href="mailto:info@pintech.com.co">info@pintech.com.co</a></div>
                    <div>www.beprocoatings.com</div>
                </td>
                <td class="header-right">
                    @if($logoBase64)
                        <img src="{{ $logoBase64 }}" alt="BePro Coatings">
                    @else
                        <div class="fallback-logo">BePro<br>Coatings</div>
                    @endif
                </td>
            </tr>
        </table>

        {{-- ── TITLE ── --}}
        <div class="title">Certificado de Inspección de Calidad</div>

        {{-- ── INFO FIELDS ── --}}
        <div class="fields">
            <div class="field"><strong>PRODUCTO:</strong> {{ $certificate['product_name'] }}</div>
            <div class="field"><strong>LOTE:</strong> {{ $certificate['lot'] }} del {{ $certificate['manufacturing_date'] ?? 'N/A' }}</div>
        </div>

        {{-- ── TESTS TABLE ── --}}
        <table>
            <thead>
                <tr>
                    <th>PRUEBA</th>
                    <th>UNIDAD</th>
                    <th>RESULTADO</th>
                    <th>LIMITE INFERIOR</th>
                    <th>LIMITE SUPERIOR</th>
                </tr>
            </thead>
            <tbody>
                @foreach($certificate['tests'] as $test)
                    <tr class="{{ $loop->odd ? 'row-stripe' : '' }}">
                        <td>{{ $test['name'] }}</td>
                        <td>{{ $test['unit'] ?? '—' }}</td>
                        <td>{{ $test['result'] ?? '—' }}</td>
                        <td>{{ $test['lower_limit'] ?? '—' }}</td>
                        <td>{{ $test['upper_limit'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- ── DATES ── --}}
        <div class="dates">
            <div><strong>FECHA DE FABRICACIÓN:</strong> {{ $certificate['manufacturing_date'] ?? 'N/A' }}</div>
            <div><strong>FECHA DE VERIFICACIÓN:</strong> {{ $certificate['verification_date'] ?? 'N/A' }}</div>
        </div>

        {{-- ── SIGNATURE ── --}}
        <div class="signature">
            <div class="label">RESPONSABLE:</div>
            @if($signatureBase64)
                <img src="{{ $signatureBase64 }}" alt="Firma">
            @endif
            <div class="name">{{ $certificate['responsible_name'] ?? 'N/A Carlos Alberto Muñoz' }}</div>
            <div class="role">{{ $certificate['responsible_role'] ?? 'Analista de producción.' }}</div>
        </div>

    </div>
</body>

</html>