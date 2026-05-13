<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\ProductionCostRecalculationService;
use App\Services\RawMaterialReferencePriceService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class RecalculateRawMaterialReferencePrice implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $rawMaterialId
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->rawMaterialId;
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    // NOTA: ShouldBeUniqueUntilProcessing previene duplicados en cola;
    //       WithoutOverlapping protege la ejecución misma (defense-in-depth)
    //       ya que la uniqueness se libera al iniciar procesamiento.
    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("raw-material-reference-price:{$this->rawMaterialId}"))
                ->releaseAfter(30)
                ->expireAfter(180),
        ];
    }

    public function handle(
        RawMaterialReferencePriceService $rawMaterialReferencePriceService,
        ProductionCostRecalculationService $productionCostRecalculationService,
    ): void {
        $currentPriceChanged = $rawMaterialReferencePriceService
            ->syncRawMaterialCurrentPrice($this->rawMaterialId);

        if ($currentPriceChanged) {
            $productionCostRecalculationService->recalculateForRawMaterial($this->rawMaterialId);
        }
    }
}
