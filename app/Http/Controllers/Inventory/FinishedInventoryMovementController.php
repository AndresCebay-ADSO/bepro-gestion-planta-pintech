<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Enums\FinishedInventoryMovementReason;
use App\Enums\InventoryMovementType;
use App\Filters\FinishedInventoryMovementFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\FinishedInventory\StoreFinishedInventoryMovementRequest;
use App\Http\Requests\Inventory\IndexFinishedInventoryMovementRequest;
use App\Models\FinishedInventoryMovement;
use App\Models\FinishedProductBatch;
use App\Models\Warehouse;
use App\Services\FinishedInventory\FinishedInventoryMovementService;
use App\Services\WarehouseContextService;
use App\Support\EnumOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FinishedInventoryMovementController extends Controller
{
    public function __construct(
        private readonly FinishedInventoryMovementService $movementService,
        private readonly WarehouseContextService $warehouseContextService,
    ) {}

    public function index(IndexFinishedInventoryMovementRequest $request): Response
    {
        $user = $request->user();
        $currentWarehouse = $user !== null
            ? $this->warehouseContextService->resolveCurrentWarehouse(
                $user,
                $request->session()->get('current_warehouse_id'),
            )
            : null;

        $movementWarehouse = $this->warehouseContextService->resolveMovementWarehouse($currentWarehouse);

        $movements = (new FinishedInventoryMovementFilter($request))
            ->apply(
                FinishedInventoryMovement::query()->with([
                    'product:id,code,name',
                    'productVariant:id,code,name,presentation_label',
                    'batch:id,product_id,entry_date',
                    'warehouse:id,name,city',
                    'productionOrder:id,order_number',
                    'createdBy:id,name',
                ])
            )
            ->latest('movement_date')
            ->latest('id')
            ->paginate(20)
            ->onEachSide(1)
            ->withQueryString();

        return Inertia::render('Inventory/FinishedMovements/Index', [
            'movements' => $movements,
            'batches' => Inertia::optional(fn () => FinishedProductBatch::query()
                ->with([
                    'product:id,code,name',
                    'productVariant:id,code,name,presentation_label',
                    'stocks',
                ])
                ->select('id', 'product_id', 'product_variant_id', 'entry_date', 'initial_quantity')
                ->fifoOrder()
                ->get()
                ->map(fn (FinishedProductBatch $batch) => [
                    'id' => $batch->id,
                    'product' => $batch->product ? ['id' => $batch->product->id, 'code' => $batch->product->code, 'name' => $batch->product->name] : null,
                    'variant' => $batch->productVariant ? ['id' => $batch->productVariant->id, 'code' => $batch->productVariant->code, 'name' => $batch->productVariant->name, 'presentation_label' => $batch->productVariant->presentation_label] : null,
                    'entry_date' => $batch->entry_date?->toDateString(),
                    'initial_quantity' => $batch->initial_quantity,
                    'stocks' => $batch->stocks->map(fn ($stock) => ['warehouse_id' => $stock->warehouse_id, 'quantity' => $stock->quantity]),
                ])),
            'warehouses' => Inertia::optional(fn () => Warehouse::query()->select('id', 'name', 'city', 'type')->get()),
            'warehouseOptions' => Warehouse::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
                ->map(fn (Warehouse $w) => ['value' => (string) $w->id, 'label' => $w->name])
                ->all(),
            'currentWarehouseId' => $movementWarehouse?->id,
            'filters' => $request->validated(),
            'typeOptions' => EnumOptions::for(InventoryMovementType::cases()),
            'reasonOptions' => EnumOptions::for(FinishedInventoryMovementReason::cases()),
            'can' => [
                'create' => Gate::allows('create', FinishedInventoryMovement::class),
            ],
        ]);
    }

    public function store(StoreFinishedInventoryMovementRequest $request): RedirectResponse
    {
        $this->authorize('create', FinishedInventoryMovement::class);

        $validated = $request->validated();
        $userId = (int) $request->user()->id;
        $reason = FinishedInventoryMovementReason::from($validated['reason']);

        if ($reason === FinishedInventoryMovementReason::Transfer) {
            $this->movementService->transfer(
                batchId: (int) $validated['finished_product_batch_id'],
                fromWarehouseId: (int) $validated['warehouse_id'],
                toWarehouseId: (int) $validated['destination_warehouse_id'],
                quantity: (string) $validated['quantity'],
                userId: $userId,
                notes: $validated['notes'] ?? null,
                movementDate: $validated['movement_date'] ? new \DateTimeImmutable($validated['movement_date']) : null,
            );
        } elseif ($validated['type'] === 'entry') {
            $this->movementService->registerEntry(
                batchId: (int) $validated['finished_product_batch_id'],
                warehouseId: (int) $validated['warehouse_id'],
                quantity: (string) $validated['quantity'],
                reason: $reason,
                userId: $userId,
                notes: $validated['notes'] ?? null,
                movementDate: $validated['movement_date'] ? new \DateTimeImmutable($validated['movement_date']) : null,
            );
        } else {
            $this->movementService->registerExit(
                batchId: (int) $validated['finished_product_batch_id'],
                warehouseId: (int) $validated['warehouse_id'],
                quantity: (string) $validated['quantity'],
                reason: $reason,
                userId: $userId,
                notes: $validated['notes'] ?? null,
                movementDate: $validated['movement_date'] ? new \DateTimeImmutable($validated['movement_date']) : null,
            );
        }

        return redirect()->route('finished-inventory-movements.index')
            ->with('success', __('Movimiento de inventario de producto terminado registrado exitosamente.'));
    }

    public function show(FinishedInventoryMovement $finishedInventoryMovement): Response
    {
        $this->authorize('view', $finishedInventoryMovement);

        return Inertia::render('Inventory/FinishedMovements/Show', [
            'movement' => $finishedInventoryMovement->load([
                'product:id,code,name',
                'productVariant:id,code,name,presentation_label',
                'batch:id,entry_date,initial_quantity',
                'warehouse:id,name,city',
                'productionOrder:id,order_number',
                'createdBy:id,name',
            ]),
        ]);
    }
}
