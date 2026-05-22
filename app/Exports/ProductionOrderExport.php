<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class ProductionOrderExport implements FromView, ShouldAutoSize, WithColumnWidths, WithDrawings, WithTitle
{
    /**
     * @param  array<string, mixed>  $orderData
     */
    public function __construct(
        private readonly array $orderData,
        private readonly ?string $logoPath = null
    ) {}

    public function columnWidths(): array
    {
        return [
            'A' => 12,
            'B' => 12,
            'C' => 12,
            'D' => 12,
            'E' => 12,
            'F' => 12,
            'G' => 12,
            'H' => 12,
            'I' => 12,
            'J' => 12,
        ];
    }

    public function title(): string
    {
        return 'Orden de Producción '.$this->orderData['order_number'];
    }

    public function drawings()
    {
        $logoPath = $this->logoPath ?? public_path('images/logo-pintech.png');

        if (! file_exists($logoPath)) {
            return [];
        }

        $drawing = new Drawing;
        $drawing->setName('Pintech Logo');
        $drawing->setDescription('Logo Corporativo');
        $drawing->setPath($logoPath);
        $drawing->setHeight(60);
        $drawing->setCoordinates('A1');

        // Ajustes para centrar visualmente en el área combinada
        $drawing->setOffsetX(85);
        $drawing->setOffsetY(1);

        return $drawing;
    }

    public function view(): View
    {
        return view('excel.production-order', [
            'order' => $this->orderData,
        ]);
    }
}
