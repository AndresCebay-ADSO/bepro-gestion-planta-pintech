<?php

namespace App\Http\Controllers;

use App\Services\AdminDashboardService;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function __construct(
        private readonly AdminDashboardService $adminDashboardService,
    ) {}

    public function index(): Response
    {
        $user = auth()->user();

        return Inertia::render('Admin/Dashboard', [
            'role' => $user->getRoleNames()->first(),
            'userName' => $user->name,
            ...$this->adminDashboardService->build(),
        ]);
    }
}
