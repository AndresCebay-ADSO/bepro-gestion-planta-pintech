<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Models\SalesOrder;
use Inertia\Inertia;
use Inertia\Response;

class ComercialController extends Controller
{
    /**
     * Mostrar panel comercial.
     */
    public function index(): Response
    {
        $user = auth()->user();

        $pendingOrders = SalesOrder::query()
            ->where('created_by', $user?->id)
            ->pending()
            ->count();

        $activeQuotes = Quotation::query()
            ->where('created_by', $user?->id)
            ->whereIn('status', [QuotationStatus::Draft->value, QuotationStatus::Sent->value])
            ->count();

        return Inertia::render('Comercial/Dashboard', [
            'role' => $user?->getRoleNames()->first(),
            'userName' => $user?->name,
            'stats' => [
                'availableProducts' => 0,
                'activeQuotes' => $activeQuotes,
                'pendingOrders' => $pendingOrders,
            ],
        ]);
    }
}
