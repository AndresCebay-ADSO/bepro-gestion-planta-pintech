<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductionOrderIngredientsSheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        private readonly ProductionOrder $order
    ) {}

    public function title(): string
    {
        return 'Ingredientes (Materia Prima)';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'CÓDIGO MP',
            'CANTIDAD PLANEADA (KG)',
            'CANTIDAD REAL (KG)',
            'COSTO UNITARIO ($)',
            'COSTO TOTAL ($)',
        ];
    }

    /**
     * @return array<int, array<int, string|float|null>>
     */
    public function array(): array
    {
        return $this->order->details->map(fn (ProductionOrderDetail $detail) => [
            $detail->rawMaterial?->code ?? 'N/A',
            (float) $detail->planned_quantity,
            $detail->actual_quantity !== null ? (float) $detail->actual_quantity : null,
            (float) $detail->unit_cost,
            (float) $detail->total_cost,
        ])->toArray();
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_NUMBER_00,
            'C' => NumberFormat::FORMAT_NUMBER_00,
            'D' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
            'E' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
        ];
    }

    /**
     * @return array<mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        $lastRow = count($this->order->details) + 1;

        return [
            // Cabecera (fila 1)
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF4A7C59'],
                ],
            ],
            // Todo el cuadro con bordes
            "A1:E{$lastRow}" => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000'],
                    ],
                ],
            ],
        ];
    }
}
