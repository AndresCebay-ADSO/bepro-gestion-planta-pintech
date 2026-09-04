<table>
    <!-- ENCABEZADO SUPERIOR (LOGO Y METADATA) -->
    <thead>
        <tr>
            <th colspan="3" rowspan="3"><!-- Espacio reservado para el logo vía WithDrawings --></th>
            <th colspan="8" rowspan="3"
                style="text-align: center; font-weight: bold; font-size: 20px; vertical-align: middle; border: 1px solid #000000;">
                ORDEN DE PRODUCCIÓN
            </th>
            <th colspan="2" style="font-weight: bold; border: 1px solid #000000; background-color: #f0f0f0;"> CÓDIGO
            </th>
            <th colspan="2" style="font-size: 10px; border: 1px solid #000000; background-color: #ffffff;">FPR-02</th>
        </tr>
        <tr>
            <th colspan="2" style="font-weight: bold; border: 1px solid #000000; background-color: #f0f0f0;"> VERSIÓN
            </th>
            <th colspan="2" style="font-size: 10px; border: 1px solid #000000; background-color: #ffffff;">2.0</th>
        </tr>
        <tr>
            <th colspan="2" style="font-weight: bold; border: 1px solid #000000; background-color: #f0f0f0;"> FECHA</th>
            <th colspan="2" style="font-size: 10px; border: 1px solid #000000; background-color: #ffffff;">DIC 2020
            </th>
        </tr>
        <tr>
            <td colspan="15" style="height: 10px;"></td>
        </tr>
    </thead>

    <tbody>
        <!-- SECCIÓN 1: DATOS GENERALES -->
        <tr>
            <th colspan="15"
                style="background-color: #4a7c59; color: #ffffff; font-weight: bold; font-size: 12px; text-align: center; vertical-align: middle; border: 1px solid #000000;">
                DATOS GENERALES DEL PRODUCTO A FABRICAR
            </th>
        </tr>
        <tr>
            <th colspan="3"
                style="font-weight: bold; border: 1px solid #000000; background-color: #ffffff; text-align: left;">ORDEN
                DE PRODUCCIÓN N°</th>
            <td colspan="4" style="border: 1px solid #000000;">{{ $order['order_number'] }}</td>
            <th colspan="2" style="font-weight: bold; border: 1px solid #000000; background-color: #ffffff;">Lote:</th>
            <td colspan="6" style="border: 1px solid #000000;">{{ filled($order['lot_number'] ?? null) ? $order['lot_number'] : $order['order_number'] }}</td>
        </tr>
        <tr>
            <th colspan="3"
                style="font-weight: bold; border: 1px solid #000000; background-color: #ffffff; text-align: left;">
                NOMBRE DEL PRODUCTO</th>
            <td colspan="8" style="border: 1px solid #000000;">{{ $order['product']['name'] ?? 'N/A' }}</td>
            <th colspan="2" style="font-weight: bold; border: 1px solid #000000; background-color: #ffffff;">FECHA</th>
            <td colspan="2" style="border: 1px solid #000000; text-align: center;">{{ !empty($order['planned_date']) ? \Carbon\Carbon::parse($order['planned_date'])->format('d/m/Y') : '—' }}</td>
        </tr>
        <tr>
            <th colspan="3"
                style="font-weight: bold; border: 1px solid #000000; background-color: #ffffff; text-align: left;">
                CANTIDAD PROYECTADA</th>
            <td colspan="4" style="border: 1px solid #000000; text-align: center;">
                {{ number_format($order['quantity'], 2) }} kg
            </td>
            <th colspan="2" style="font-weight: bold; border: 1px solid #000000; background-color: #ffffff;">ENVASES A
                USAR</th>
            <td colspan="6" style="border: 1px solid #000000;">
                @foreach($order['packaging_plans'] as $plan)
                    {{ $plan['planned_units'] }} x
                    {{ $plan['product_variant']['presentation_label'] ?? 'N/A' }}@if(!$loop->last), @endif
                @endforeach
            </td>
        </tr>
        <tr>
            <th colspan="3"
                style="font-weight: bold; border: 1px solid #000000; background-color: #ffffff; text-align: left;">
                CANTIDAD RESULTADO</th>
            <td colspan="4" style="border: 1px solid #000000; text-align: center;">
                {{ !empty($order['yield_real_quantity']) ? number_format($order['yield_real_quantity'], 2) . ' kg' : '---' }}
            </td>
            <th colspan="2" style="font-weight: bold; border: 1px solid #000000; background-color: #ffffff;">RESPONSABLE
            </th>
            <td colspan="6" style="border: 1px solid #000000;">{{ $order['responsible_name'] ?? '---' }}</td>
        </tr>

        <tr>
            <td colspan="15" style="height: 10px;"></td>
        </tr>

        <!-- SECCIÓN 2: PESAJE Y TRANSFORMACIÓN -->
        <tr>
            <th colspan="15"
                style="background-color: #4a7c59; color: #ffffff; font-weight: bold; text-align: center; border: 1px solid #000000;">
                PESAJE Y TRANSFORMACION MATERIA PRIMA
            </th>
        </tr>
        <tr>
            <th colspan="3"
                style="font-weight: bold; border: 1px solid #000000; background-color: #daeef3; text-align: left;">
                INICIO AGITACIÓN</th>
            <td colspan="4" style="border: 1px solid #000000;">{{ filled($order['agitation_start_time'] ?? null) ? app(\App\Services\TimezoneService::class)->formatPlantDateTime($order['agitation_start_time'], 'H:i') : '---' }}</td>
            <th colspan="3"
                style="font-weight: bold; border: 1px solid #000000; background-color: #daeef3; text-align: left;">FIN
                AGITACIÓN</th>
            <td colspan="5" style="border: 1px solid #000000;">{{ filled($order['agitation_end_time'] ?? null) ? app(\App\Services\TimezoneService::class)->formatPlantDateTime($order['agitation_end_time'], 'H:i') : '---' }}</td>
        </tr>
        <tr>
            <th colspan="3"
                style="font-weight: bold; border: 1px solid #000000; background-color: #daeef3; text-align: left;">
                INICIO ENVASADO</th>
            <td colspan="4" style="border: 1px solid #000000;">{{ filled($order['packaging_start_time'] ?? null) ? app(\App\Services\TimezoneService::class)->formatPlantDateTime($order['packaging_start_time'], 'H:i') : '---' }}</td>
            <th colspan="3"
                style="font-weight: bold; border: 1px solid #000000; background-color: #daeef3; text-align: left;">FIN
                ENVASADO</th>
            <td colspan="5" style="border: 1px solid #000000;">{{ filled($order['packaging_end_time'] ?? null) ? app(\App\Services\TimezoneService::class)->formatPlantDateTime($order['packaging_end_time'], 'H:i') : '---' }}</td>
        </tr>

        <tr>
            <td colspan="15" style="height: 10px;"></td>
        </tr>

        <!-- SECCIÓN 3: MATERIA PRIMA Y CANTIDADES -->
        @php
            $pdfMaterials = $order['pdf_materials'] ?? ['mode' => 'steps', 'rows' => []];
            $pdfMode = $pdfMaterials['mode'] ?? 'steps';
            $pdfRows = $pdfMaterials['rows'] ?? [];
            $pdfTotals = $pdfMaterials['totals'] ?? ['planned_quantity' => '0'];
        @endphp
        <tr>
            <th colspan="15"
                style="background-color: #4a7c59; color: #ffffff; font-weight: bold; text-align: center; border: 1px solid #000000;">
                MATERIA PRIMA Y CANTIDADES
            </th>
        </tr>
        <tr style="font-weight: bold; background-color: #f0f0f0; text-align: center;">
            <th colspan="3" style="border: 1px solid #000000;">COD</th>
            <th colspan="3" style="border: 1px solid #000000;">CANT. KG</th>
            <th colspan="6" style="border: 1px solid #000000;">DESCRIPCIÓN</th>
            <th colspan="3" style="border: 1px solid #000000;">{{ $pdfMode === 'consolidated' ? 'AGREGADO' : 'ESTADO' }}</th>
        </tr>
        @if($pdfMode === 'consolidated')
            @foreach($pdfRows as $row)
                <tr>
                    <td colspan="3" style="border: 1px solid #000000; text-align: center;">
                        {{ $row['raw_material_code'] }}
                    </td>
                    <td colspan="3" style="border: 1px solid #000000; text-align: center;">
                        {{ number_format($row['planned_quantity'], 2) }}
                    </td>
                    <td colspan="6" style="border: 1px solid #000000;">{{ $row['raw_material_name'] }}</td>
                    <td colspan="3" style="border: 1px solid #000000; text-align: center;">
                        {{ isset($row['actual_quantity']) ? number_format($row['actual_quantity'], 2).' kg' : '' }}
                    </td>
                </tr>
            @endforeach
            <tr style="font-weight: bold;">
                <td colspan="3" style="border: 1px solid #000000; text-align: right; background-color: #f2f2f2;">TOTAL</td>
                <td colspan="3" style="border: 1px solid #000000; text-align: center;">
                    {{ number_format((float) ($pdfTotals['planned_quantity'] ?? 0), 2) }}
                    kg
                </td>
                <td colspan="9" style="border: 1px solid #000000; background-color: #f2f2f2;"></td>
            </tr>
        @else
            @foreach($pdfRows as $row)
                <tr>
                    <td colspan="3" style="border: 1px solid #000000; text-align: center;">
                        {{ $row['step_order'] }}. {{ $row['raw_material_code'] }}
                    </td>
                    <td colspan="3" style="border: 1px solid #000000; text-align: center;">
                        {{ number_format($row['planned_quantity'], 2) }}
                    </td>
                    <td colspan="6" style="border: 1px solid #000000;">{{ $row['raw_material_name'] }}</td>
                    <td colspan="3" style="border: 1px solid #000000; text-align: center;">AGREGADO</td>
                </tr>
            @endforeach
            <tr style="font-weight: bold;">
                <td colspan="3" style="border: 1px solid #000000; text-align: right; background-color: #f2f2f2;">TOTAL</td>
                <td colspan="3" style="border: 1px solid #000000; text-align: center;">
                    {{ number_format((float) ($pdfTotals['planned_quantity'] ?? 0), 2) }}
                    kg
                </td>
                <td colspan="9" style="border: 1px solid #000000; background-color: #f2f2f2;"></td>
            </tr>
        @endif

        <tr>
            <td colspan="15" style="height: 10px;"></td>
        </tr>

        <!-- SECCIÓN 3.1: AJUSTES DE LÍNEA -->
        @if(!empty($order['line_adjustments']) && count($order['line_adjustments']) > 0)
            <tr>
                <th colspan="15"
                    style="background-color: #c67b1c; color: #ffffff; font-weight: bold; text-align: center; border: 1px solid #000000;">
                    AJUSTES DE LÍNEA (MPs ADICIONALES)
                </th>
            </tr>
            <tr style="font-weight: bold; background-color: #fce4d6; text-align: center;">
                <th colspan="3" style="border: 1px solid #000000;">COD</th>
                <th colspan="3" style="border: 1px solid #000000;">CANT. KG</th>
                <th colspan="6" style="border: 1px solid #000000;">MOTIVO / DESCRIPCIÓN</th>
                <th colspan="3" style="border: 1px solid #000000;">ESTADO</th>
            </tr>
            @foreach($order['line_adjustments'] as $adj)
                <tr>
                    <td colspan="3" style="border: 1px solid #000000; text-align: center;">
                        {{ $adj['raw_material']['code'] ?? 'N/A' }}
                    </td>
                    <td colspan="3" style="border: 1px solid #000000; text-align: center;">
                        {{ number_format($adj['quantity'], 4) }}
                    </td>
                    <td colspan="6" style="border: 1px solid #000000;">{{ $adj['reason'] }} {{ $adj['notes'] ? '- '.$adj['notes'] : '' }}</td>
                    <td colspan="3" style="border: 1px solid #000000; text-align: center;">AGREGADO</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="15" style="height: 10px;"></td>
            </tr>
        @endif

        <tr>
            <td colspan="15" style="height: 10px;"></td>
        </tr>

        <!-- SECCIÓN 4: RESULTADOS DE CALIDAD -->
        <tr>
            <th colspan="15"
                style="background-color: #4a7c59; color: #ffffff; font-weight: bold; text-align: center; border: 1px solid #000000;">
                RESULTADOS DE CALIDAD
            </th>
        </tr>
        <tr>
            <th colspan="4"
                style="font-weight: bold; border: 1px solid #000000; background-color: #ffffff; text-align: left;">
                VISCOSIDAD A 25 C (KU)</th>
            <td colspan="3" style="border: 1px solid #000000; text-align: center;">{{ $order['viscosity_ku'] ?? '---' }}
            </td>
            <th colspan="4"
                style="font-weight: bold; border: 1px solid #000000; background-color: #ffffff; text-align: left;">
                MOLIENDA (HG)</th>
            <td colspan="4" style="border: 1px solid #000000; text-align: center;">{{ $order['grinding_hg'] ?? '---' }}
            </td>
        </tr>
        @if(isset($order['total_bulk_cost']))
            <tr>
                <th colspan="4"
                    style="font-weight: bold; border: 1px solid #000000; background-color: #ffffff; text-align: left;">COSTO
                    TOTAL GRANEL</th>
                <td colspan="11" style="border: 1px solid #000000; font-weight: bold;">$
                    {{ number_format($order['total_bulk_cost'], 2) }}
                </td>
            </tr>
        @endif

        <tr>
            <td colspan="15" style="height: 10px;"></td>
        </tr>

        <!-- NOTAS -->
        <tr>
            <th colspan="15"
                style="font-weight: bold; border: 1px solid #000000; background-color: #f2f2f2; text-align: left;">NOTAS
                Y OBSERVACIONES:</th>
        </tr>
        <tr>
            <td colspan="15" style="border: 1px solid #000000; height: 50px; vertical-align: top;">
                {{ $order['notes'] ?? 'Sin observaciones adicionales.' }}
            </td>
        </tr>
    </tbody>
</table>
