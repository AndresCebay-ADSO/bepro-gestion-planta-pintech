<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Models\Alert;
use App\Models\InventoryBatch;
use App\Models\RawMaterial;
use Illuminate\Support\Carbon;

class AlertService
{
    /** @var list<array{id: int, message: string, severity: string, type: string, type_label: string}> */
    private array $createdInRequest = [];

    public function __construct(
        private readonly DecimalCalculator $calculator
    ) {}

    public function unresolvedCount(): int
    {
        return Alert::query()
            ->where('is_resolved', false)
            ->count();
    }

    /**
     * @return array{stock_bajo: int, vencimiento_proximo: int, variacion_precio: int, paint_development_request: int}
     */
    public function unresolvedBreakdown(): array
    {
        $counts = Alert::query()
            ->selectRaw('type, COUNT(*) as total')
            ->where('is_resolved', false)
            ->groupBy('type')
            ->pluck('total', 'type');

        return [
            'stock_bajo' => (int) ($counts[AlertType::StockBajo->value] ?? 0),
            'vencimiento_proximo' => (int) ($counts[AlertType::VencimientoProximo->value] ?? 0),
            'variacion_precio' => (int) ($counts[AlertType::VariacionPrecio->value] ?? 0),
            'paint_development_request' => (int) ($counts[AlertType::PaintDevelopmentRequest->value] ?? 0),
        ];
    }

    /**
     * @return list<array{
     *     id: int,
     *     type: string,
     *     type_label: string,
     *     severity: string,
     *     severity_label: string,
     *     message: string,
     *     created_at: string|null,
     *     raw_material_code: string|null
     * }>
     */
    public function recentUnresolved(int $limit = 5): array
    {
        return Alert::query()
            ->with(['rawMaterial:id,code'])
            ->where('is_resolved', false)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Alert $alert): array => [
                'id' => $alert->id,
                'type' => $alert->type->value,
                'type_label' => $alert->type->label(),
                'severity' => $alert->severity->value,
                'severity_label' => $alert->severity->label(),
                'message' => $alert->message,
                'created_at' => $alert->created_at?->toIso8601String(),
                'raw_material_code' => $alert->rawMaterial?->code,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, message: string, severity: string, type: string, type_label: string}>
     */
    public function pullCreatedInRequest(): array
    {
        $created = $this->createdInRequest;
        $this->createdInRequest = [];

        return $created;
    }

    public function evaluateLowStock(int $rawMaterialId): void
    {
        $rawMaterial = RawMaterial::query()
            ->select(['id', 'code', 'minimum_stock', 'tracks_inventory', 'is_active'])
            ->withSum('inventoryBatches as available_stock', 'remaining_quantity')
            ->find($rawMaterialId);

        if ($rawMaterial === null || ! $rawMaterial->is_active || ! $rawMaterial->tracks_inventory) {
            $this->autoResolveActive(AlertType::StockBajo, $rawMaterialId, null);

            return;
        }

        $minimumStock = (string) $rawMaterial->minimum_stock;
        $availableStock = (string) ($rawMaterial->available_stock ?? '0');

        if (
            ! $this->calculator->isPositive($minimumStock)
            || $this->calculator->cmp($availableStock, $minimumStock) > 0
        ) {
            $this->autoResolveActive(AlertType::StockBajo, $rawMaterialId, null);

            return;
        }

        $severity = $this->calculator->cmp($availableStock, '0') <= 0
            ? AlertSeverity::Alta
            : AlertSeverity::Media;

        $message = sprintf(
            '%s: stock bajo (%s / mínimo %s)',
            $rawMaterial->code,
            $this->formatQuantity($availableStock),
            $this->formatQuantity($minimumStock),
        );

        $this->createOrRefresh(
            type: AlertType::StockBajo,
            rawMaterialId: $rawMaterialId,
            batchId: null,
            severity: $severity,
            message: $message,
        );
    }

    public function evaluateBatchExpiry(int $batchId): void
    {
        $batch = InventoryBatch::query()
            ->select(['id', 'raw_material_id', 'lot_number', 'remaining_quantity', 'expiry_date'])
            ->with(['rawMaterial:id,code,alert_days_before_expiry,is_active'])
            ->find($batchId);

        if ($batch === null) {
            return;
        }

        $rawMaterial = $batch->rawMaterial;

        if (
            $rawMaterial === null
            || ! $rawMaterial->is_active
            || $batch->expiry_date === null
            || ! $this->calculator->isPositive((string) $batch->remaining_quantity)
        ) {
            $this->autoResolveActive(AlertType::VencimientoProximo, (int) $batch->raw_material_id, (int) $batch->id);

            return;
        }

        $today = Carbon::today();
        $expiryDate = $batch->expiry_date->startOfDay();
        $alertDays = max(0, (int) $rawMaterial->alert_days_before_expiry);
        $thresholdDate = $today->copy()->addDays($alertDays);

        if ($expiryDate->greaterThan($thresholdDate)) {
            $this->autoResolveActive(AlertType::VencimientoProximo, (int) $batch->raw_material_id, (int) $batch->id);

            return;
        }

        $lotLabel = $batch->lot_number !== null && $batch->lot_number !== ''
            ? $batch->lot_number
            : '#'.$batch->id;

        if ($expiryDate->lessThan($today)) {
            $message = sprintf(
                '%s lote %s vencido desde %s',
                $rawMaterial->code,
                $lotLabel,
                $expiryDate->format('Y-m-d'),
            );
            $severity = AlertSeverity::Alta;
        } else {
            $message = sprintf(
                '%s lote %s vence el %s',
                $rawMaterial->code,
                $lotLabel,
                $expiryDate->format('Y-m-d'),
            );
            $severity = AlertSeverity::Media;
        }

        $this->createOrRefresh(
            type: AlertType::VencimientoProximo,
            rawMaterialId: (int) $batch->raw_material_id,
            batchId: (int) $batch->id,
            severity: $severity,
            message: $message,
        );
    }

    public function scanExpiringBatches(): int
    {
        $processed = 0;

        InventoryBatch::query()
            ->select(['id'])
            ->whereNotNull('expiry_date')
            ->where('remaining_quantity', '>', 0)
            ->orderBy('id')
            ->chunkById(100, function ($batches) use (&$processed): void {
                foreach ($batches as $batch) {
                    $this->evaluateBatchExpiry((int) $batch->id);
                    $processed++;
                }
            });

        return $processed;
    }

    public function evaluatePriceVariation(RawMaterial $rawMaterial, ?string $previousPrice, ?string $newPrice): void
    {
        if ($rawMaterial->price_variation_threshold === null) {
            return;
        }

        if (
            $previousPrice === null
            || $newPrice === null
            || ! $this->calculator->isPositive($previousPrice)
        ) {
            return;
        }

        if ($this->calculator->cmp($previousPrice, $newPrice) === 0) {
            return;
        }

        $threshold = (string) $rawMaterial->price_variation_threshold;
        $variationPercentage = $this->calculateVariationPercentage($previousPrice, $newPrice);

        if ($this->calculator->cmp($variationPercentage, $threshold) <= 0) {
            return;
        }

        $signedVariation = $this->calculateSignedVariationPercentage($previousPrice, $newPrice);
        $sign = $this->calculator->cmp($signedVariation, '0') >= 0 ? '+' : '';

        $message = sprintf(
            '%s: %s%s%% ($%s → $%s)',
            $rawMaterial->code,
            $sign,
            $this->formatQuantity($variationPercentage),
            $this->formatPrice($previousPrice),
            $this->formatPrice($newPrice),
        );

        $severity = $this->calculator->cmp($variationPercentage, $this->calculator->mul($threshold, '2', 4)) >= 0
            ? AlertSeverity::Alta
            : AlertSeverity::Media;

        $this->createOrRefresh(
            type: AlertType::VariacionPrecio,
            rawMaterialId: (int) $rawMaterial->id,
            batchId: null,
            severity: $severity,
            message: $message,
        );
    }

    public function resolve(Alert $alert, int $userId): void
    {
        if ($alert->is_resolved) {
            return;
        }

        $alert->update([
            'is_resolved' => true,
            'resolved_by' => $userId,
            'resolved_at' => now(),
            'updated_by' => $userId,
        ]);
    }

    public function createPaintDevelopmentAlert(
        AlertType $type,
        AlertSeverity $severity,
        string $message,
    ): void {
        $alert = Alert::create([
            'type' => $type,
            'severity' => $severity,
            'message' => $message,
            'is_resolved' => false,
        ]);

        $payload = [
            'id' => $alert->id,
            'message' => $message,
            'severity' => $severity->value,
            'type' => $type->value,
            'type_label' => $type->label(),
        ];

        $this->createdInRequest[] = $payload;
        session()->push('new_alerts', $payload);
    }

    private function createOrRefresh(
        AlertType $type,
        int $rawMaterialId,
        ?int $batchId,
        AlertSeverity $severity,
        string $message,
    ): void {
        $alert = Alert::query()
            ->where('type', $type)
            ->where('raw_material_id', $rawMaterialId)
            ->when(
                $batchId === null,
                fn ($query) => $query->whereNull('batch_id'),
                fn ($query) => $query->where('batch_id', $batchId),
            )
            ->where('is_resolved', false)
            ->first();

        if ($alert !== null) {
            $alert->update([
                'severity' => $severity,
                'message' => $message,
            ]);

            return;
        }

        $alert = Alert::create([
            'type' => $type,
            'raw_material_id' => $rawMaterialId,
            'batch_id' => $batchId,
            'severity' => $severity,
            'message' => $message,
            'is_resolved' => false,
        ]);

        $payload = [
            'id' => $alert->id,
            'message' => $message,
            'severity' => $severity->value,
            'type' => $type->value,
            'type_label' => $type->label(),
        ];

        $this->createdInRequest[] = $payload;
        session()->push('new_alerts', $payload);
    }

    private function autoResolveActive(AlertType $type, int $rawMaterialId, ?int $batchId): void
    {
        Alert::query()
            ->where('type', $type)
            ->where('raw_material_id', $rawMaterialId)
            ->when(
                $batchId === null,
                fn ($query) => $query->whereNull('batch_id'),
                fn ($query) => $query->where('batch_id', $batchId),
            )
            ->where('is_resolved', false)
            ->update([
                'is_resolved' => true,
                'resolved_at' => now(),
            ]);
    }

    private function calculateVariationPercentage(string $previousPrice, string $newPrice): string
    {
        $diff = $this->calculator->sub($newPrice, $previousPrice, 10);
        $ratio = $this->calculator->div($diff, $previousPrice, 10);

        return $this->calculator->abs($this->calculator->mul($ratio, '100', 4), 4);
    }

    private function calculateSignedVariationPercentage(string $previousPrice, string $newPrice): string
    {
        $diff = $this->calculator->sub($newPrice, $previousPrice, 10);
        $ratio = $this->calculator->div($diff, $previousPrice, 10);

        return $this->calculator->mul($ratio, '100', 4);
    }

    private function formatQuantity(string $value): string
    {
        return rtrim(rtrim($value, '0'), '.');
    }

    private function formatPrice(string $value): string
    {
        return number_format((float) $value, 2, '.', ',');
    }
}
