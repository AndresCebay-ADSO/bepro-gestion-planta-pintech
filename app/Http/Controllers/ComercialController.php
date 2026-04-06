<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class ComercialController extends Controller
{
    /**
     * Mostrar panel comercial.
     */
    public function index()
    {
        return Inertia::render('Comercial/Dashboard', [
            'role' => auth()->user()->getRoleNames()->first(),
            'userName' => auth()->user()->name,
            'stats' => [
                'availableProducts' => 0,
                'activeQuotes' => 0,
                'pendingOrders' => 0,
            ],
        ]);
    }
}
