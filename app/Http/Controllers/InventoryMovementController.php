<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\InventoryMovementType;
use App\Enums\Permission;
use App\Filters\InventoryMovementFilter;
use App\Http\Requests\Inventory\IndexInventoryMovementRequest;
use App\Http\Requests\Inventory\StoreInventoryMovementRequest;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\RawMaterial;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\WarehouseContextService;
use App\Support\EnumOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InventoryMovementController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly WarehouseContextService $warehouseContextService
    ) {}

    public function index(IndexInventoryMovementRequest $request): Response
    {
        $user = $request->user();
        $canViewCosts = $user?->can(Permission::CostsView->value) ?? false;
        $currentWarehouse = $user !== null
            ? $this->warehouseContextService->resolveCurrentWarehouse(
                $user,
                $request->session()->get('current_warehouse_id'),
            )
            : null;

        $movementWarehouse = $this->warehouseContextService->resolveMovementWarehouse($currentWarehouse);

        $movements = (new InventoryMovementFilter($request))
            ->apply(
                InventoryMovement::query()->with([
                    'rawMaterial:id,code',
                    'batch:id,lot_number,raw_material_id',
                    'warehouse:id,name,city',
                    'productionOrder:id,order_number',
                    'createdBy:id,name',
                ])
            )
            ->latest('movement_date')
            ->latest('id')
            ->paginate(20)
            ->onEachSide(1)
            ->withQueryString()
            ->through(fn (InventoryMovement $movement) => $canViewCosts ? $movement : $movement->makeHidden('cost_price'));

        return Inertia::render('Inventory/Movements/Index', [
            'movements' => $movements,
            'rawMaterials' => Inertia::optional(fn () => RawMaterial::query()->select('id', 'code')->where('is_active', true)->orderBy('code')->get()),
            // Lotes con precio para el formulario de alta: solo para quien puede registrar movimientos.
            'batches' => Inertia::optional(fn () => Gate::denies('create', InventoryMovement::class) ? [] : InventoryBatch::query()
                ->when(
                    $movementWarehouse !== null,
                    fn ($query) => $query->where('warehouse_id', $movementWarehouse->id),
                    fn ($query) => $query->whereRaw('1 = 0')
                )
                ->where('remaining_quantity', '>', 0)
                ->select('id', 'raw_material_id', 'warehouse_id', 'lot_number', 'remaining_quantity', 'unit_price')
                ->orderByDesc('id')
                ->get()),
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
            'can' => [
                'create' => Gate::allows('create', InventoryMovement::class),
                'viewCosts' => $canViewCosts,
            ],
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

        $canViewCosts = auth()->user()?->can(Permission::CostsView->value) ?? false;

        $inventoryMovement->load([
            'rawMaterial:id,code',
            'batch:id,lot_number,remaining_quantity',
            'productionOrder:id,order_number',
            'createdBy:id,name',
        ]);

        if (! $canViewCosts) {
            $inventoryMovement->makeHidden('cost_price');
        }

        return Inertia::render('Inventory/Movements/Show', [
            'movement' => $inventoryMovement,
            'can' => [
                'viewCosts' => $canViewCosts,
            ],
        ]);
    }
}
