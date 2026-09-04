<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Orden de Producción - {{ $order['order_number'] }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            color: #1a1a1a;
            line-height: 1.3;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td,
        th {
            border: 1px solid #333;
            padding: 4px 6px;
            vertical-align: middle;
            text-align: left;
        }

        .section-header {
            background-color: #4a7c59;
            color: #fff;
            font-weight: bold;
            text-align: center;
            font-size: 10px;
            padding: 5px;
            text-transform: uppercase;
        }

        .section-header-alt {
            background-color: #2e5a3a;
            color: #fff;
            font-weight: bold;
            text-align: center;
            font-size: 10px;
            padding: 5px;
            text-transform: uppercase;
        }

        .label {
            font-weight: bold;
            background-color: #f0f0f0;
            font-size: 9px;
            text-transform: uppercase;
        }

        .value {
            font-size: 10px;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .text-bold {
            font-weight: bold;
        }

        .write-space {
            min-height: 20px;
            border-bottom: 1px solid #999;
        }

        .small-text {
            font-size: 8px;
            color: #555;
        }

        .logo-img {
            max-height: 55px;
            max-width: 160px;
        }

        .page-break {
            page-break-after: always;
        }

        .instructions-text {
            font-style: italic;
            padding: 6px;
        }

        .footer {
            position: fixed;
            bottom: 10px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #888;
        }
    </style>
</head>

<body>

    {{-- ① CABECERA --}}
    <table>
        <tr>
            <td rowspan="3" style="width: 25%; text-align: center; border-right: 1px solid #333;">
                @if($logoBase64)
                    <img src="{{ $logoBase64 }}" class="logo-img" alt="Pintech">
                @else
                    <strong style="font-size: 16px;">PINTECH</strong>
                @endif
            </td>
            <td rowspan="3" style="width: 45%; text-align: center; font-size: 18px; font-weight: bold;">
                ORDEN DE PRODUCCION
            </td>
            <td class="label" style="width: 15%;">CÓDIGO:</td>
            <td class="value" style="width: 15%;">FPR-02</td>
        </tr>
        <tr>
            <td class="label">VERSIÓN:</td>
            <td class="value">2.0</td>
        </tr>
        <tr>
            <td class="label">FECHA:</td>
            <td class="value">{{ $generatedAt }}</td>
        </tr>
    </table>

    {{-- ② DATOS GENERALES DEL PRODUCTO A FABRICAR --}}
    <table style="margin-top: 8px;">
        <tr>
            <td colspan="9" class="section-header">DATOS GENERALES DEL PRODUCTO A FABRICAR</td>
        </tr>
        <tr>
            <td colspan="2" class="label">ORDEN DE PRODUCCION N°</td>
            <td colspan="3" class="value">
                {{ $order['order_number'] }}
            </td>
            <td class="label">Lote</td>
            <td colspan="3" class="value">
                {{ $order['lot_number'] ?? $order['order_number'] }}{{ $order['planned_date'] ? ' del ' . \Carbon\Carbon::parse($order['planned_date'])->translatedFormat('d \d\e F Y') : '' }}
            </td>
        </tr>
        <tr>
            <td colspan="2" class="label">NOMBRE DEL PRODUCTO</td>
            <td colspan="3" class="value">
                {{ $order['product']['name'] ?? 'N/A' }}
                {{ !empty($order['product']['code']) ? '(' . $order['product']['code'] . ')' : '' }}
            </td>
            <td class="label">FECHA</td>
            @php
                $plannedDate = $order['planned_date'] ? \Carbon\Carbon::parse($order['planned_date']) : null;
            @endphp
            <td class="value text-center">{{ $plannedDate?->format('d') ?? '__' }}</td>
            <td class="value text-center">{{ $plannedDate?->format('m') ?? '__' }}</td>
            <td class="value text-center">{{ $plannedDate?->format('Y') ?? '__' }}</td>
        </tr>
        <tr>
            <td colspan="2" class="label">CANTIDAD PROYECTADA A FABRICAR</td>
            <td class="value text-center">{{ number_format($order['quantity'], 2) }}</td>
            <td class="label">ENVASES A USAR</td>
            <td colspan="5" class="value">
                @if(count($order['packaging_plans']) > 0)
                    @foreach($order['packaging_plans'] as $plan)
                        {{ number_format($plan['planned_units'], 0) }}
                        {{ $plan['product_variant']['presentation_label'] ?? 'uds' }}@if(!$loop->last), @endif
                    @endforeach
                @else
                    N/A
                @endif
            </td>
        </tr>
        <tr>
            <td colspan="2" class="label">CANTIDAD RESULTADO</td>
            <td class="value text-center">
                {{ $order['actual_quantity'] !== null ? number_format($order['actual_quantity'], 2) : '' }}</td>
            <td class="label">ENVASES A USAR</td>
            <td colspan="5" class="value">
                @if(count($order['packaging_plans']) > 0)
                    @foreach($order['packaging_plans'] as $plan)
                        @if($plan['actual_units'] !== null)
                            {{ number_format($plan['actual_units'], 0) }}
                            {{ $plan['product_variant']['presentation_label'] ?? 'uds' }}@if(!$loop->last), @endif
                        @endif
                    @endforeach
                @endif
            </td>
        </tr>
    </table>

    {{-- ③ PESAJE Y TRANSFORMACIÓN MATERIA PRIMA --}}
    <table style="margin-top: 8px;">
        <tr>
            <td colspan="6" class="section-header">PESAJE Y TRANSFORMACION MATERIA PRIMA</td>
        </tr>
        <tr>
            <td class="label" style="width: 20%;">HORA DE INICIO DE AGITACIÓN</td>
            <td class="value" style="width: 13%;">
                {{ filled($order['agitation_start_time'] ?? null) ? app(\App\Services\TimezoneService::class)->formatPlantDateTime($order['agitation_start_time'], 'H:i') : '' }}
            </td>
            <td class="label" style="width: 20%;">HORA FINALIZACIÓN AGITACIÓN</td>
            <td class="value" style="width: 13%;">
                {{ filled($order['agitation_end_time'] ?? null) ? app(\App\Services\TimezoneService::class)->formatPlantDateTime($order['agitation_end_time'], 'H:i') : '' }}
            </td>
            <td class="label" style="width: 17%;">OBSERVACIONES</td>
            <td class="value" style="width: 17%;"></td>
        </tr>
        <tr>
            <td class="label">HORA DE INICIO DE ENVASADO</td>
            <td class="value">
                {{ filled($order['packaging_start_time'] ?? null) ? app(\App\Services\TimezoneService::class)->formatPlantDateTime($order['packaging_start_time'], 'H:i') : '' }}
            </td>
            <td class="label">HORA FINALIZACIÓN ENVASADO</td>
            <td class="value">
                {{ filled($order['packaging_end_time'] ?? null) ? app(\App\Services\TimezoneService::class)->formatPlantDateTime($order['packaging_end_time'], 'H:i') : '' }}
            </td>
            <td class="label">DERRAMES EN EL ENVASADO</td>
            <td class="value">
                @if($order['spillage_quantity'] > 0)
                    SI — {{ number_format($order['spillage_quantity'], 2) }} kg
                @else
                    NO
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">HORA DE INICIO DE EMPACADO</td>
            <td class="value"></td>
            <td class="label">HORA FINALIZACIÓN EMPACADO</td>
            <td class="value"></td>
            <td colspan="2" class="small-text text-center">
                Si la respuesta es afirmativa indicar la cantidad aproximada
            </td>
        </tr>
    </table>

    {{-- ④ INSTRUCCIONES PARA PRODUCCIÓN --}}
    <table style="margin-top: 8px;">
        <tr>
            <td colspan="6" class="section-header-alt">INSTRUCCIONES PARA PRODUCCIÓN</td>
        </tr>
        <tr>
            <td class="label" style="width: 30%;">INDICACIONES PARA LA FABRICACIÓN:</td>
            <td colspan="5" class="instructions-text">
                {{ $order['notes'] ?? 'N/A' }}
            </td>
        </tr>
    </table>

    {{-- ⑤ MATERIA PRIMA Y CANTIDADES --}}
    @php
        $pdfMaterials = $order['pdf_materials'] ?? ['mode' => 'steps', 'rows' => []];
        $pdfMode = $pdfMaterials['mode'] ?? 'steps';
        $pdfRows = $pdfMaterials['rows'] ?? [];
        $pdfTotals = $pdfMaterials['totals'] ?? ['kg' => '0', 'grams' => '0'];
    @endphp
    <table style="margin-top: 8px;">
        <tr>
            <td colspan="{{ $pdfMode === 'consolidated' ? 5 : 6 }}" class="section-header">MATERIA PRIMA Y CANTIDADES</td>
        </tr>
        @if($pdfMode === 'consolidated')
            <tr>
                <th class="label text-center" style="width: 15%;">COD</th>
                <th class="label text-center" style="width: 18%;">CANTIDAD EN KG</th>
                <th class="label text-center" style="width: 18%;">CANTIDAD EN GRAMOS</th>
                <th class="label text-center" style="width: 34%;">OBSERVACIONES</th>
                <th class="label text-center" style="width: 15%;">AGREGADO</th>
            </tr>
            @foreach($pdfRows as $row)
                @php
                    $kg = $row['display_kg'] ?? '0';
                    $grams = $row['display_grams'] ?? '0';
                @endphp
                <tr>
                    <td class="text-center">{{ $row['raw_material_code'] }}</td>
                    <td class="text-center">{{ $kg > 0 ? number_format($kg, 2) : '' }}</td>
                    <td class="text-center">{{ $grams > 0 ? number_format($grams, 0) : '' }}</td>
                    <td></td>
                    <td class="text-center">
                        {{ isset($row['actual_quantity']) ? number_format($row['actual_quantity'], 2) : '' }}
                    </td>
                </tr>
            @endforeach
            <tr class="text-bold">
                <td class="text-center label">TOTAL</td>
                <td class="text-center">{{ number_format((float) ($pdfTotals['kg'] ?? 0), 2) }}</td>
                <td class="text-center">{{ (float) ($pdfTotals['grams'] ?? 0) > 0 ? number_format((float) $pdfTotals['grams'], 0) : '0' }}</td>
                <td></td>
                <td></td>
            </tr>
        @else
            <tr>
                <th class="label text-center" style="width: 6%;">#</th>
                <th class="label text-center" style="width: 14%;">COD</th>
                <th class="label text-center" style="width: 18%;">CANTIDAD EN KG</th>
                <th class="label text-center" style="width: 18%;">CANTIDAD EN GRAMOS</th>
                <th class="label text-center" style="width: 29%;">OBSERVACIONES</th>
                <th class="label text-center" style="width: 15%;">AGREGADO</th>
            </tr>
            @foreach($pdfRows as $row)
                @php
                    $kg = $row['display_kg'] ?? '0';
                    $grams = $row['display_grams'] ?? '0';
                @endphp
                <tr>
                    <td class="text-center">{{ $row['step_order'] }}</td>
                    <td class="text-center">{{ $row['raw_material_code'] }}</td>
                    <td class="text-center">{{ $kg > 0 ? number_format($kg, 2) : '' }}</td>
                    <td class="text-center">{{ $grams > 0 ? number_format($grams, 0) : '' }}</td>
                    <td></td>
                    <td class="text-center">
                        {{ isset($row['actual_quantity']) ? number_format($row['actual_quantity'], 2) : '' }}
                    </td>
                </tr>
            @endforeach
            <tr class="text-bold">
                <td class="text-center label" colspan="2">TOTAL</td>
                <td class="text-center">{{ number_format((float) ($pdfTotals['kg'] ?? 0), 2) }}</td>
                <td class="text-center">{{ (float) ($pdfTotals['grams'] ?? 0) > 0 ? number_format((float) $pdfTotals['grams'], 0) : '0' }}</td>
                <td colspan="2"></td>
            </tr>
        @endif
    </table>

    {{-- ⑤.1 AJUSTES DE LÍNEA (MPs fuera de fórmula) --}}
    @if(!empty($order['line_adjustments']) && count($order['line_adjustments']) > 0)
        <table style="margin-top: 8px;">
            <tr>
                <td colspan="4" class="section-header" style="background-color: #c67b1c;">AJUSTES DE LÍNEA (MPs ADICIONALES)</td>
            </tr>
            <tr>
                <th class="label text-center" style="width: 20%;">MATERIA PRIMA</th>
                <th class="label text-center" style="width: 20%;">CANTIDAD</th>
                <th class="label text-center" style="width: 40%;">MOTIVO</th>
                <th class="label text-center" style="width: 20%;">OBSERVACIONES</th>
            </tr>
            @foreach($order['line_adjustments'] as $adj)
                <tr>
                    <td class="text-center">{{ $adj['raw_material']['code'] ?? 'N/A' }}</td>
                    <td class="text-center">{{ number_format($adj['quantity'], 4) }}</td>
                    <td>{{ $adj['reason'] }}</td>
                    <td>{{ $adj['notes'] ?? '' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    {{-- ⑥ NOMBRE RESPONSABLE --}}
    <table style="margin-top: 8px;">
        <tr>
            <td class="section-header-alt" style="width: 30%;">NOMBRE RESPONSABLE</td>
            <td class="value" style="font-size: 12px; padding: 8px;">
                {{ $order['responsible_name'] ?? '' }}
            </td>
        </tr>
    </table>

    {{-- ⑦ RESULTADOS DE CALIDAD --}}
    <table style="margin-top: 8px;">
        <tr>
            <td colspan="6" class="section-header">RESULTADOS DE CALIDAD</td>
        </tr>
        <tr>
            <td class="label" style="width: 22%;">REQUIERE MOLIENDA<br><span class="small-text">(En la Escala de la
                    piedra HG)</span></td>
            <td class="value text-center" style="width: 10%;">
                {{ $order['grinding_hg'] !== null ? number_format($order['grinding_hg'], 1) : '' }}
            </td>
            <td class="label" style="width: 18%;">TIEMPO TOTAL PARA MOLIENDA</td>
            <td class="value text-center" style="width: 10%;"></td>
            <td class="label" style="width: 15%;">OBSERVACIONES</td>
            <td class="value" style="width: 25%;"></td>
        </tr>
        <tr>
            <td class="label">RESULTADO DE VISCOSIDAD<br><span class="small-text">a 25°C (KU)</span></td>
            <td class="value text-center">
                {{ $order['viscosity_ku'] !== null ? number_format($order['viscosity_ku'], 1) : '' }}
            </td>
            <td class="label">AJUSTES PARA LA VISCOSIDAD</td>
            <td class="value text-center" colspan="3">
                SI _____ &nbsp;&nbsp;&nbsp; NO _____
                &nbsp;&nbsp;&nbsp;
                <span class="small-text">Si la respuesta es sí, indique los ajustes</span>
            </td>
        </tr>
        <tr>
            <td class="label">CORRIDO DE PODER CUBRIENTE</td>
            <td class="value text-center"></td>
            <td class="label">AJUSTES PARA EL PODER CUBRIENTE</td>
            <td class="value text-center" colspan="3">
                SI _____ &nbsp;&nbsp;&nbsp; NO _____
                &nbsp;&nbsp;&nbsp;
                <span class="small-text">Si la respuesta es sí, indique los ajustes</span>
            </td>
        </tr>
    </table>

    {{-- ⑧ OBSERVACIONES FINALES --}}
    <table style="margin-top: 8px;">
        <tr>
            <td class="section-header-alt">OBSERVACIONES FINALES</td>
        </tr>
        <tr>
            <td class="label">INFORMACION A TENER EN CUENTA DE ESTA FABRICACIÓN:</td>
        </tr>
        <tr>
            <td style="min-height: 60px; padding: 10px; font-size: 10px;">
                {{ $order['notes'] ?? '' }}
                <br><br><br>
            </td>
        </tr>
    </table>

    {{-- RESUMEN FINANCIERO (solo si la orden está completada y el usuario puede ver costos) --}}
    @if($order['status'] === 'completed' && isset($order['total_bulk_cost']))
        <table style="margin-top: 8px;">
            <tr>
                <td colspan="4" class="section-header">RESUMEN FINANCIERO</td>
            </tr>
            <tr>
                <td class="label" style="width: 25%;">COSTO TOTAL GRANEL</td>
                <td class="value" style="width: 25%;">${{ number_format($order['total_bulk_cost'], 2) }}</td>
                <td class="label" style="width: 25%;">COSTO TOTAL TERMINADO</td>
                <td class="value" style="width: 25%;">${{ number_format($order['total_finished_cost'], 2) }}</td>
            </tr>
            @if($order['yield_percentage'] !== null)
                <tr>
                    <td class="label">RENDIMIENTO REAL</td>
                    <td class="value">
                        {{ $order['yield_real_quantity'] !== null ? number_format($order['yield_real_quantity'], 2) . ' kg' : 'N/A' }}
                    </td>
                    <td class="label">RENDIMIENTO TEÓRICO</td>
                    <td class="value">
                        {{ $order['yield_theoretical_quantity'] !== null ? number_format($order['yield_theoretical_quantity'], 2) . ' kg' : 'N/A' }}
                    </td>
                </tr>
                <tr>
                    <td class="label">VARIANZA</td>
                    <td class="value">
                        {{ $order['yield_variance_quantity'] !== null ? number_format($order['yield_variance_quantity'], 2) . ' kg' : 'N/A' }}
                    </td>
                    <td class="label">% RENDIMIENTO</td>
                    <td class="value">{{ number_format($order['yield_percentage'], 1) }}%</td>
                </tr>
            @endif
        </table>
    @endif

    <div class="footer">
        Generado por Pintech OS — {{ $generatedAt }}
    </div>

</body>

</html>
