<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ProductionOrder;
use App\Services\QualityInspectionCertificateService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateQualityInspectionCertificateJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [5, 10, 20];

    public function __construct(
        public readonly ProductionOrder $order,
        public readonly int $userId
    ) {}

    public function handle(QualityInspectionCertificateService $service): void
    {
        $service->generateForCompletedOrder($this->order, $this->userId);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Fallo crítico al generar el certificado de calidad para la orden de producción.', [
            'order_id' => $this->order->id,
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);
    }
}
