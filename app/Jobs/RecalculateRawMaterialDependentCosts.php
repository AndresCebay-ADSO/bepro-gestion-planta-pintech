<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\ProductionCostRecalculationService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Recalcula lo que depende del precio de una materia prima cuando ese precio se escribe a mano (las que no controlan
 * inventario, como el agua o las etiquetas). `RecalculateRawMaterialReferencePrice` no sirve aquí: recalcula el precio
 * desde los lotes de compra, que estas no tienen, y al no ver cambio no recalcula nada.
 */
class RecalculateRawMaterialDependentCosts implements ShouldBeUniqueUntilProcessing, ShouldQueue
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

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("raw-material-dependent-costs:{$this->rawMaterialId}"))
                ->releaseAfter(30)
                ->expireAfter(180),
        ];
    }

    public function handle(ProductionCostRecalculationService $productionCostRecalculationService): void
    {
        $productionCostRecalculationService->recalculateForRawMaterial($this->rawMaterialId);
    }
}
