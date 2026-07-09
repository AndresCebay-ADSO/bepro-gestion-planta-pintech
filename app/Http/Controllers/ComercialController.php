<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SalesOrder;
use Inertia\Inertia;

class ComercialController extends Controller
{
    /**
     * Mostrar panel comercial.
     */
    public function index()
    {
        $user = auth()->user();

        $pendingOrders = SalesOrder::query()
            ->where('created_by', $user?->id)
            ->pending()
            ->count();

        return Inertia::render('Comercial/Dashboard', [
            'role' => $user?->getRoleNames()->first(),
            'userName' => $user?->name,
            'stats' => [
                'availableProducts' => 0,
                'activeQuotes' => 0,
                'pendingOrders' => $pendingOrders,
            ],
        ]);
    }
}
