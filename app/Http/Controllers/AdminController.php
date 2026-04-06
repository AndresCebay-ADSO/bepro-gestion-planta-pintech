<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class AdminController extends Controller
{
    /**
     * Mostrar panel de administración.
     */
    public function index()
    {
        return Inertia::render('Admin/Dashboard', [
            'role' => auth()->user()->getRoleNames()->first(),
            'userName' => auth()->user()->name,
            'stats' => [
                'totalUsers' => \App\Models\User::count(),
                'totalProducts' => 0, // Se llenará con datos reales
                'totalWarehouses' => 0,
            ],
        ]);
    }
}
