<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\FinishedInventory;
use App\Services\FinishedInventoryQueryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinishedInventoryController extends Controller
{
    public function __construct(
        private readonly FinishedInventoryQueryService $finishedInventoryQueryService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', FinishedInventory::class);

        $user = $request->user();

        return Inertia::render('Inventory/FinishedInventory/Index', $this->finishedInventoryQueryService->buildIndexData(
            $user,
            $request->input('search'),
            $request->integer('warehouse_id') ?: null,
            $request->integer('product_id') ?: null,
        ));
    }
}
