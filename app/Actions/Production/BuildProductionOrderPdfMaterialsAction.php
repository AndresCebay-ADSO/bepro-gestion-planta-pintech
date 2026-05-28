<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Enums\ProductionOrderStatus;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Services\DecimalCalculator;

class BuildProductionOrderPdfMaterialsAction
{
    public function __construct(
        private readonly DecimalCalculator $calculator
    ) {}

    /**
     * Filas para PDF/Excel: pasos ordenados por step_order (órdenes no completadas)
     * o consolidado por materia prima (orden completada).
     *
     * @return array{mode: string, rows: list<array<string, mixed>>, totals: array{planned_quantity: string, kg: string, grams: string, actual_quantity: string}}
     */
    public function execute(ProductionOrder $order): array
    {
        $order->loadMissing('details.rawMaterial');

        $details = $order->details->sortBy('step_order')->values();

        if ($order->status === ProductionOrderStatus::Completed) {
            $rows = $details->groupBy('raw_material_id')
                ->map(function ($group) {
                    /** @var ProductionOrderDetail $first */
                    $first = $group->first();

                    $plannedArray = $group->map(fn (ProductionOrderDetail $d) => (string) $d->planned_quantity)->all();
                    $actualArray = $group->map(fn (ProductionOrderDetail $d) => (string) ($d->actual_quantity ?? '0'))->all();

                    return [
                        'raw_material_code' => $first->rawMaterial->code ?? 'N/A',
                        'raw_material_name' => $first->rawMaterial->code ?? 'N/A',
                        'planned_quantity' => $this->calculator->sum($plannedArray, 4),
                        'actual_quantity' => $this->calculator->sum($actualArray, 4),
                    ];
                })
                ->values()
                ->sortBy('raw_material_code')
                ->map(fn (array $row): array => $this->withDisplayQuantities($row))
                ->values()
                ->all();

            return [
                'mode' => 'consolidated',
                'rows' => $rows,
                'totals' => $this->buildTotals($rows),
            ];
        }

        $rows = [];

        foreach ($details as $detail) {
            $rows[] = $this->withDisplayQuantities([
                'step_order' => (int) $detail->step_order,
                'raw_material_code' => $detail->rawMaterial->code ?? 'N/A',
                'raw_material_name' => $detail->rawMaterial->code ?? 'N/A',
                'planned_quantity' => (string) $detail->planned_quantity,
                'actual_quantity' => $detail->actual_quantity !== null ? (string) $detail->actual_quantity : null,
            ]);
        }

        return [
            'mode' => 'steps',
            'rows' => $rows,
            'totals' => $this->buildTotals($rows),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function withDisplayQuantities(array $row): array
    {
        $plannedQuantity = (string) ($row['planned_quantity'] ?? '0');

        $row['display_kg'] = $this->calculator->cmp($plannedQuantity, '1', 4) >= 0
            ? $plannedQuantity
            : '0';
        $row['display_grams'] = $this->calculator->cmp($plannedQuantity, '1', 4) < 0
            ? $this->calculator->mul($plannedQuantity, '1000', 4)
            : '0';

        return $row;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{planned_quantity: string, kg: string, grams: string, actual_quantity: string}
     */
    private function buildTotals(array $rows): array
    {
        $plannedQuantities = [];
        $kilograms = [];
        $grams = [];
        $actualQuantities = [];

        foreach ($rows as $row) {
            $plannedQuantities[] = (string) ($row['planned_quantity'] ?? '0');
            $kilograms[] = (string) ($row['display_kg'] ?? '0');
            $grams[] = (string) ($row['display_grams'] ?? '0');
            $actualQuantities[] = (string) ($row['actual_quantity'] ?? '0');
        }

        return [
            'planned_quantity' => $this->calculator->sum($plannedQuantities, 4),
            'kg' => $this->calculator->sum($kilograms, 4),
            'grams' => $this->calculator->sum($grams, 4),
            'actual_quantity' => $this->calculator->sum($actualQuantities, 4),
        ];
    }
}
