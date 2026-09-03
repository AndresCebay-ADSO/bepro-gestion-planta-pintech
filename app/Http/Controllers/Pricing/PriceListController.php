<?php

namespace App\Http\Controllers\Pricing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\IndexPriceListRequest;
use App\Services\PriceListService;
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
    public function index(IndexPriceListRequest $request): Response
    {
        return Inertia::render('Prices/Index', $this->priceListService->buildList(
            $request->user(),
            $request,
        ));
    }
}
