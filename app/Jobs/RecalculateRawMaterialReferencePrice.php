<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\RawMaterial;
use App\Services\AlertService;
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
        AlertService $alertService,
    ): void {
        $currentPriceChanged = $rawMaterialReferencePriceService
            ->syncRawMaterialCurrentPrice($this->rawMaterialId);

        if ($currentPriceChanged) {
            $this->evaluatePriceVariationAlert($alertService);
            $productionCostRecalculationService->recalculateForRawMaterial($this->rawMaterialId);
        }
    }

    private function evaluatePriceVariationAlert(AlertService $alertService): void
    {
        $rawMaterial = RawMaterial::query()
            ->select(['id', 'code', 'current_price', 'previous_price', 'price_variation_threshold'])
            ->find($this->rawMaterialId);

        if ($rawMaterial === null) {
            return;
        }

        $alertService->evaluatePriceVariation(
            rawMaterial: $rawMaterial,
            previousPrice: $rawMaterial->previous_price !== null ? (string) $rawMaterial->previous_price : null,
            newPrice: $rawMaterial->current_price !== null ? (string) $rawMaterial->current_price : null,
        );
    }
}
