<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\IndexFinishedInventoryRequest;
use App\Services\FinishedInventoryQueryService;
use Inertia\Inertia;
use Inertia\Response;

class FinishedInventoryController extends Controller
{
    public function __construct(
        private readonly FinishedInventoryQueryService $finishedInventoryQueryService,
    ) {}

    public function index(IndexFinishedInventoryRequest $request): Response
    {
        return Inertia::render('Inventory/FinishedInventory/Index', $this->finishedInventoryQueryService->buildIndexData(
            $request->user(),
            $request,
        ));
    }
}
