<?php

namespace App\Http\Controllers\Pricing;

use App\Http\Controllers\Controller;
use App\Services\PriceListService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PriceListController extends Controller
{
    public function __construct(
        private readonly PriceListService $priceListService,
    ) {}

    /**
     * Display the price list for admin and comercial roles.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Prices/Index', $this->priceListService->buildList(
            $user,
            $request->input('search'),
        ));
    }
}
