<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DashboardService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const SUPPORTED_ROLES = ['admin', 'produccion', 'operador', 'comercial'];

    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    /**
     * Renderiza el dashboard global según el rol del usuario.
     */
    public function index(): Response
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        $role = $user->getRoleNames()->first();

        abort_unless(in_array($role, self::SUPPORTED_ROLES, true), 403);

        return Inertia::render('Dashboard/Index', [
            'role' => $role,
            'userName' => $user->name,
            ...$this->dashboardService->build($user),
        ]);
    }
}
