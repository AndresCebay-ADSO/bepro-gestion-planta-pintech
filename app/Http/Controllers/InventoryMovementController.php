<?php

namespace App\Http\Controllers;

use App\Http\Requests\Inventory\StoreInventoryMovementRequest;
use App\Http\Requests\Inventory\UpdateInventoryMovementRequest;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\ProductionOrder;
use App\Models\RawMaterial;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InventoryMovementController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    public function index(\Illuminate\Http\Request $request): Response
    {
        $this->authorize('viewAny', InventoryMovement::class);

        $search = $request->input('search');

        $movements = InventoryMovement::query()
            ->with([
                'rawMaterial:id,code',
                'batch:id,lot_number,raw_material_id',
                'productionOrder:id,order_number',
                'createdBy:id,name',
            ])
            ->when($search, function ($query, $search) {
                $query->whereHas('rawMaterial', function ($q) use ($search) {
                    $q->where('code', 'ILIKE', "%{$search}%");
                });
            })
            ->latest('movement_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Inventory/Movements/Index', [
            'movements' => $movements,
            'filters' => [
                'search' => $search,
            ],
            'can' => [
                'create' => Gate::allows('create', InventoryMovement::class),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', InventoryMovement::class);

        return Inertia::render('Inventory/Movements/Create', [
            'rawMaterials' => RawMaterial::query()->select('id', 'code')->where('is_active', true)->orderBy('code')->get(),
            'batches' => InventoryBatch::query()->select('id', 'raw_material_id', 'lot_number', 'remaining_quantity')->orderByDesc('id')->get(),
            'productionOrders' => ProductionOrder::query()->select('id', 'order_number', 'status')->orderByDesc('id')->get(),
        ]);
    }

    public function store(StoreInventoryMovementRequest $request): RedirectResponse
    {
        $this->authorize('create', InventoryMovement::class);

        $this->inventoryService->storeMovement($request->validated(), (int) $request->user()->id);

        return redirect()->route('inventory-movements.index')->with('success', __('Movimiento de inventario registrado exitosamente.'));
    }

    public function show(InventoryMovement $inventoryMovement): Response
    {
        $this->authorize('view', $inventoryMovement);

        return Inertia::render('Inventory/Movements/Show', [
            'movement' => $inventoryMovement->load([
                'rawMaterial:id,code',
                'batch:id,lot_number,remaining_quantity',
                'productionOrder:id,order_number',
                'createdBy:id,name',
            ]),
            'can' => [
                'update' => Gate::allows('update', $inventoryMovement),
                'delete' => Gate::allows('delete', $inventoryMovement),
            ],
        ]);
    }

    public function edit(InventoryMovement $inventoryMovement): Response
    {
        $this->authorize('update', $inventoryMovement);

        return Inertia::render('Inventory/Movements/Edit', [
            'movement' => $inventoryMovement,
            'rawMaterials' => RawMaterial::query()->select('id', 'code')->where('is_active', true)->orderBy('code')->get(),
            'batches' => InventoryBatch::query()->select('id', 'raw_material_id', 'lot_number', 'remaining_quantity')->orderByDesc('id')->get(),
            'productionOrders' => ProductionOrder::query()->select('id', 'order_number', 'status')->orderByDesc('id')->get(),
        ]);
    }

    public function update(UpdateInventoryMovementRequest $request, InventoryMovement $inventoryMovement): RedirectResponse
    {
        $this->authorize('update', $inventoryMovement);

        $this->inventoryService->updateMovement($inventoryMovement, $request->validated());

        return redirect()->route('inventory-movements.index')->with('success', __('Movimiento de inventario actualizado exitosamente.'));
    }

    public function destroy(InventoryMovement $inventoryMovement): RedirectResponse
    {
        $this->authorize('delete', $inventoryMovement);

        $this->inventoryService->deleteMovement($inventoryMovement);

        return redirect()->route('inventory-movements.index')->with('success', __('Movimiento de inventario eliminado exitosamente.'));
    }
}
