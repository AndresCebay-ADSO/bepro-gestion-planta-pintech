<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Solicitud de Desarrollo {{ $request['request_number'] }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; margin: 24px; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .meta { color: #666; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #ddd; vertical-align: top; }
        th { width: 35%; color: #555; font-weight: normal; }
        h2 { font-size: 14px; margin-top: 20px; margin-bottom: 8px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .badge-draft { background: #e5e7eb; color: #374151; }
        .badge-submitted { background: #dbeafe; color: #1e40af; }
        .badge-in_review { background: #fef3c7; color: #92400e; }
        .badge-approved { background: #d1fae5; color: #065f46; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <h1>Solicitud de desarrollo de una pintura nueva</h1>
    <div class="meta">
        Número: <strong>{{ $request['request_number'] }}</strong> &nbsp;|&nbsp;
        Estado: <span class="badge badge-{{ $request['status'] }}">{{ $request['status_label'] }}</span> &nbsp;|&nbsp;
        Fecha: {{ $request['created_at'] }}
    </div>

    <h2>1. Identificación</h2>
    <table>
        <tr><th>Proyecto</th><td>{{ $request['project_name'] }}</td></tr>
        <tr><th>Cliente</th><td>{{ $request['client_name'] ?? '—' }}</td></tr>
        <tr><th>Responsable</th><td>{{ $request['responsible'] }}</td></tr>
        <tr><th>Ciudad</th><td>{{ $request['city'] }}</td></tr>
        <tr><th>Fecha requerida para la muestra</th><td>{{ $request['sample_due_date'] ?? '—' }}</td></tr>
        <tr><th>Producto actual</th><td>{{ $request['current_product'] ?? '—' }}</td></tr>
    </table>

    <h2>2. Contexto — Proyecto, sustrato y exposición</h2>
    <table>
        @foreach($request['context_payload'] as $key => $value)
            <tr>
                <th>{{ ucfirst(str_replace('_', ' ', $key)) }}</th>
                <td>{{ is_array($value) ? implode(', ', $value) : ($value ?: '—') }}</td>
            </tr>
        @endforeach
    </table>

    <h2>3. Desempeño — Función, resistencia y acabado</h2>
    <table>
        @foreach($request['performance_payload'] as $key => $value)
            <tr>
                <th>{{ ucfirst(str_replace('_', ' ', $key)) }}</th>
                <td>{{ is_array($value) ? implode(', ', $value) : ($value ?: '—') }}</td>
            </tr>
        @endforeach
    </table>

    <h2>4. Aplicación — Método, espesores y tecnología</h2>
    <table>
        @foreach($request['application_payload'] as $key => $value)
            <tr>
                <th>{{ ucfirst(str_replace('_', ' ', $key)) }}</th>
                <td>{{ is_array($value) ? implode(', ', $value) : ($value ?: '—') }}</td>
            </tr>
        @endforeach
    </table>

    <h2>5. Especificaciones — Control, suministro y aprobación</h2>
    <table>
        @foreach($request['specifications_payload'] as $key => $value)
            <tr>
                <th>{{ ucfirst(str_replace('_', ' ', $key)) }}</th>
                <td>{{ is_array($value) ? implode(', ', $value) : ($value ?: '—') }}</td>
            </tr>
        @endforeach
    </table>

    @if($request['review_notes'])
        <h2>Revisión</h2>
        <table>
            <tr><th>Notas de revisión</th><td>{{ $request['review_notes'] }}</td></tr>
            <tr><th>Revisado por</th><td>{{ $request['reviewer']['name'] ?? '—' }}</td></tr>
            <tr><th>Fecha de revisión</th><td>{{ $request['reviewed_at'] ?? '—' }}</td></tr>
        </table>
    @endif

    <div style="margin-top: 24px; font-size: 10px; color: #888;">
        Generado el {{ $generatedAt }} — Pintech OS
    </div>
</body>
</html>
