<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use App\Models\ProductionOrder;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductionOrderGeneralSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        private readonly ProductionOrder $order
    ) {}

    public function title(): string
    {
        return 'Orden de Producción';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['CAMPO', 'VALOR'];
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    public function array(): array
    {
        $order = $this->order;

        return [
            ['Número de Orden', $order->order_number],
            ['Estado', $order->status->value],
            ['Producto', $order->product?->name ?? 'N/A'],
            ['Código Producto', $order->product?->code ?? 'N/A'],
            ['Fórmula Versión', $order->formula?->version !== null ? (string) $order->formula->version : 'N/A'],
            ['Bodega', $order->warehouse?->name ?? 'N/A'],
            ['Cantidad Proyectada', (string) $order->quantity],
            ['Cantidad Resultado', $order->actual_quantity !== null ? (string) $order->actual_quantity : ''],
            ['Fecha Planificada', optional($order->planned_date)?->toDateString() ?? ''],
            ['Fecha Finalización', optional($order->completion_date)?->toDateString() ?? ''],
            ['Responsable', $order->responsible_name ?? ''],
            ['Viscosidad (KU)', $order->viscosity_ku !== null ? (string) $order->viscosity_ku : ''],
            ['Molienda (HG)', $order->grinding_hg !== null ? (string) $order->grinding_hg : ''],
            ['Derrames (kg)', (string) $order->spillage_quantity],
            ['Rendimiento Real (kg)', $order->yield_real_quantity !== null ? (string) $order->yield_real_quantity : ''],
            ['Rendimiento Teórico (kg)', $order->yield_theoretical_quantity !== null ? (string) $order->yield_theoretical_quantity : ''],
            ['Varianza (kg)', $order->yield_variance_quantity !== null ? (string) $order->yield_variance_quantity : ''],
            ['% Rendimiento', $order->yield_percentage !== null ? (string) $order->yield_percentage : ''],
            ['Notas', $order->notes ?? ''],
        ];
    }

    /**
     * @return array<mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            // Cabecera (fila 1)
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF4A7C59'],
                ],
            ],
            // Columna de Etiquetas (columna A)
            'A' => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFF0F0F0'],
                ],
            ],
        ];
    }
}
