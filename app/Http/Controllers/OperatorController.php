<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\OperatorDashboardService;
use Inertia\Inertia;
use Inertia\Response;

class OperatorController extends Controller
{
    public function __construct(
        private readonly OperatorDashboardService $operatorDashboardService,
    ) {}

    public function index(): Response
    {
        $user = auth()->user();

        return Inertia::render('Operator/Dashboard', [
            'role' => $user->getRoleNames()->first(),
            'userName' => $user->name,
            ...$this->operatorDashboardService->build(),
        ]);
    }
}
