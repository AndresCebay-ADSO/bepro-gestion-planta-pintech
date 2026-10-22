<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\ProductionCostRecalculationService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Recalcula el costo de los productos cuya fórmula de referencia convierte con una unidad cuya equivalencia se corrigió
 * (galón de 3,785 a 3,7854 L). Fuera de la petición, como el cambio de precio de una materia prima: una unidad puede
 * estar en cientos de fórmulas.
 */
class RecalculateUnitDependentCosts implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $unitOfMeasureId
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->unitOfMeasureId;
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
            (new WithoutOverlapping("unit-dependent-costs:{$this->unitOfMeasureId}"))
                ->releaseAfter(30)
                ->expireAfter(180),
        ];
    }

    public function handle(ProductionCostRecalculationService $productionCostRecalculationService): void
    {
        $productionCostRecalculationService->recalculateForUnit($this->unitOfMeasureId);
    }
}
