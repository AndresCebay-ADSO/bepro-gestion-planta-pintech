<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ProductionDashboardService;
use Inertia\Inertia;
use Inertia\Response;

class ProductionController extends Controller
{
    public function __construct(
        private readonly ProductionDashboardService $productionDashboardService,
    ) {}

    /**
     * Mostrar panel de producción.
     */
    public function index(): Response
    {
        $user = auth()->user();

        return Inertia::render('Production/Dashboard', [
            'role' => $user->getRoleNames()->first(),
            'userName' => $user->name,
            ...$this->productionDashboardService->build(),
        ]);
    }
}
