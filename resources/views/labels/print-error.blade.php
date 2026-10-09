{{--
    Error al abrir una estampita de lote. Se muestra en la pestaña que abrió el botón de imprimir: volver atrás ahí
    cargaría una segunda copia de la orden.

    $message: string — por qué no se imprime.
    $orderUrl: string — la orden, por si la pestaña no se puede cerrar sola.
--}}
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>No se pudo abrir la estampita</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            box-sizing: border-box;
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: #f8fafc;
            color: #0f172a;
        }

        .card {
            max-width: 420px;
            padding: 24px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 18px;
        }

        p {
            margin: 0 0 20px;
            line-height: 1.5;
            color: #475569;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        button,
        a {
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 14px;
            text-decoration: none;
            cursor: pointer;
        }

        button {
            border: 0;
            background: #0f172a;
            color: #fff;
        }

        a {
            border: 1px solid #cbd5e1;
            color: #0f172a;
        }
    </style>
</head>

<body>
    <div class="card">
        <h1>No se pudo abrir la estampita</h1>
        <p>{{ $message }}</p>
        <div class="actions">
            <button type="button" onclick="window.close()">Cerrar pestaña</button>
            <a href="{{ $orderUrl }}">Ir a la orden</a>
        </div>
    </div>
</body>

</html>
