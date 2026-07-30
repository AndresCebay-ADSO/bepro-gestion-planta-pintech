<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Enums\FinishedInventoryMovementReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\FinishedInventory\StoreFinishedInventoryMovementRequest;
use App\Http\Requests\FinishedInventory\UpdateFinishedInventoryMovementRequest;
use App\Models\FinishedInventoryMovement;
use App\Models\FinishedProductBatch;
use App\Models\Warehouse;
use App\Services\FinishedInventory\FinishedInventoryMovementService;
use App\Services\WarehouseContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FinishedInventoryMovementController extends Controller
{
    public function __construct(
        private readonly FinishedInventoryMovementService $movementService,
        private readonly WarehouseContextService $warehouseContextService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', FinishedInventoryMovement::class);

        $search = strtolower((string) $request->input('search'));
        $user = $request->user();
        $currentWarehouse = $user !== null
            ? $this->warehouseContextService->resolveCurrentWarehouse(
                $user,
                $request->session()->get('current_warehouse_id'),
            )
            : null;

        $movementWarehouse = $this->warehouseContextService->resolveMovementWarehouse($currentWarehouse);

        $movements = FinishedInventoryMovement::query()
            ->with([
                'product:id,code,name',
                'productVariant:id,code,name,presentation_label',
                'batch:id,product_id,entry_date',
                'warehouse:id,name,city',
                'productionOrder:id,order_number',
                'createdBy:id,name',
            ])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search): void {
                    $q->whereHas('product', function ($pq) use ($search): void {
                        $pq->whereRaw('LOWER(code) LIKE ?', ["%{$search}%"])
                            ->orWhereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
                    })
                        ->orWhereHas('productVariant', function ($vq) use ($search): void {
                            $vq->whereRaw('LOWER(code) LIKE ?', ["%{$search}%"])
                                ->orWhereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
                        });
                });
            })
            ->latest('movement_date')
            ->latest('id')
            ->paginate(20)
            ->onEachSide(1)
            ->withQueryString();

        return Inertia::render('Inventory/FinishedMovements/Index', [
            'movements' => $movements,
            'batches' => Inertia::optional(fn () => FinishedProductBatch::query()
                ->available()
                ->when(
                    $movementWarehouse !== null,
                    fn ($query) => $query->whereHas('stocks', fn ($q) => $q->forWarehouse($movementWarehouse->id)->available()),
                    fn ($query) => $query->whereRaw('1 = 0')
                )
                ->with(['product:id,code,name', 'productVariant:id,code,name,presentation_label', 'stocks'])
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
            'currentWarehouseId' => $movementWarehouse?->id,
            'filters' => [
                'search' => $search,
            ],
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
            'can' => [
                'update' => Gate::allows('update', $finishedInventoryMovement),
                'delete' => Gate::allows('delete', $finishedInventoryMovement),
            ],
        ]);
    }

    public function edit(FinishedInventoryMovement $finishedInventoryMovement): Response
    {
        $this->authorize('update', $finishedInventoryMovement);

        return Inertia::render('Inventory/FinishedMovements/Edit', [
            'movement' => $finishedInventoryMovement->load([
                'product:id,code,name',
                'productVariant:id,code,name',
            ]),
            'batches' => FinishedProductBatch::query()
                ->where(function ($query) use ($finishedInventoryMovement): void {
                    $query->available();

                    if ($finishedInventoryMovement->finished_product_batch_id !== null) {
                        $query->orWhere('id', $finishedInventoryMovement->finished_product_batch_id);
                    }
                })
                ->with(['product:id,code,name', 'productVariant:id,code,name,presentation_label'])
                ->select('id', 'product_id', 'product_variant_id', 'entry_date', 'initial_quantity')
                ->fifoOrder()
                ->get(),
            'warehouses' => Warehouse::query()->select('id', 'name', 'city', 'type')->get(),
        ]);
    }

    public function update(UpdateFinishedInventoryMovementRequest $request, FinishedInventoryMovement $finishedInventoryMovement): RedirectResponse
    {
        $this->authorize('update', $finishedInventoryMovement);

        $this->movementService->updateMovement($finishedInventoryMovement, $request->validated());

        return redirect()->route('finished-inventory-movements.index')
            ->with('success', __('Movimiento de inventario de producto terminado actualizado exitosamente.'));
    }

    public function destroy(FinishedInventoryMovement $finishedInventoryMovement): RedirectResponse
    {
        $this->authorize('delete', $finishedInventoryMovement);

        $this->movementService->deleteMovement($finishedInventoryMovement);

        return redirect()->route('finished-inventory-movements.index')
            ->with('success', __('Movimiento de inventario de producto terminado eliminado exitosamente.'));
    }
}
