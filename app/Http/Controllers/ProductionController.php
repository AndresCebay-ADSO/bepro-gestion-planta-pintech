<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class ProductionController extends Controller
{
    /**
     * Mostrar panel de producción.
     */
    public function index()
    {
        return Inertia::render('Production/Dashboard', [
            'role' => auth()->user()->getRoleNames()->first(),
            'userName' => auth()->user()->name,
            'stats' => [
                'pendingOrders' => 0,
                'activeOrders' => 0,
                'completedToday' => 0,
            ],
        ]);
    }
}
