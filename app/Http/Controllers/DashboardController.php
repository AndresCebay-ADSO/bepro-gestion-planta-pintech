<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\User;
use App\Services\DashboardService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    /**
     * Renderiza el dashboard global. La vista se elige por permisos (DashboardService).
     */
    public function index(): Response
    {
        $user = auth()->user();

        abort_unless($user instanceof User && $user->can(Permission::DashboardView->value), 403);

        return Inertia::render('Dashboard/Index', [
            'roleLabel' => SystemRole::labelFor($user->getRoleNames()->first()),
            'userName' => $user->name,
            ...$this->dashboardService->build($user),
        ]);
    }
}
