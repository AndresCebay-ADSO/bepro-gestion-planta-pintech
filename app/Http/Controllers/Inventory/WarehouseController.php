<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouses\AssignUsersRequest;
use App\Http\Requests\Warehouses\StoreWarehouseRequest;
use App\Http\Requests\Warehouses\UpdateWarehouseRequest;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\WarehouseContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController extends Controller
{
    public function __construct(private readonly WarehouseContextService $warehouseContextService) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Warehouse::class);

        $search = strtolower(trim((string) $request->input('search')));
        $user = $request->user();

        $query = Warehouse::query()
            ->withCount('users')
            ->latest('id');

        if (! $user->hasRole('admin')) {
            $query->whereHas('users', fn ($usersQuery) => $usersQuery->where('users.id', $user->id));
        }

        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery
                    ->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(city) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(address) LIKE ?', ["%{$search}%"]);
            });
        }

        $warehouses = $query
            ->paginate(15)
            ->withQueryString()
            ->through(function (Warehouse $warehouse) use ($user): array {
                return [
                    'id' => $warehouse->id,
                    'name' => $warehouse->name,
                    'city' => $warehouse->city,
                    'address' => $warehouse->address,
                    'type' => $warehouse->type,
                    'is_active' => $warehouse->is_active,
                    'users_count' => $warehouse->users_count,
                    'can' => [
                        'view' => Gate::forUser($user)->allows('view', $warehouse),
                        'update' => Gate::forUser($user)->allows('update', $warehouse),
                        'delete' => Gate::forUser($user)->allows('delete', $warehouse),
                    ],
                ];
            });

        return Inertia::render('Inventory/Warehouses/Index', [
            'warehouses' => $warehouses,
            'filters' => [
                'search' => $search,
            ],
            'can' => [
                'create' => Gate::allows('create', Warehouse::class),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Warehouse::class);

        return Inertia::render('Inventory/Warehouses/Create');
    }

    public function store(StoreWarehouseRequest $request): RedirectResponse
    {
        $this->authorize('create', Warehouse::class);

        Warehouse::create($request->validated());

        return redirect()
            ->route('warehouses.index')
            ->with('success', __('Bodega registrada exitosamente.'));
    }

    public function show(Warehouse $warehouse): Response
    {
        $this->authorize('view', $warehouse);

        $warehouse->load([
            'users:id,name,email',
            'finishedInventories' => fn ($query) => $query
                ->select('id', 'warehouse_id', 'product_id', 'quantity')
                ->with(['product:id,code,name'])
                ->orderByDesc('id'),
        ]);

        return Inertia::render('Inventory/Warehouses/Show', [
            'warehouse' => $warehouse,
            'can' => [
                'update' => Gate::allows('update', $warehouse),
                'delete' => Gate::allows('delete', $warehouse),
                'assignUsers' => Gate::allows('update', $warehouse),
            ],
        ]);
    }

    public function edit(Warehouse $warehouse): Response
    {
        $this->authorize('update', $warehouse);

        return Inertia::render('Inventory/Warehouses/Edit', [
            'warehouse' => $warehouse,
        ]);
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $this->authorize('update', $warehouse);

        $warehouse->update($request->validated());

        return redirect()
            ->route('warehouses.index')
            ->with('success', __('Bodega actualizada exitosamente.'));
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        $this->authorize('delete', $warehouse);

        $hasFinishedInventory = $warehouse->finishedInventories()->exists();
        if ($hasFinishedInventory) {
            return back()->with('error', __('No se puede eliminar la bodega porque tiene inventario de producto terminado.'));
        }

        $warehouse->delete();

        return redirect()
            ->route('warehouses.index')
            ->with('success', __('Bodega eliminada exitosamente.'));
    }

    public function assignUsersPage(Warehouse $warehouse): Response
    {
        $this->authorize('update', $warehouse);

        $warehouse->load('users:id,name,email');

        $selectedByUser = $warehouse->users->keyBy('id');
        $users = User::query()
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get()
            ->map(function (User $user) use ($selectedByUser): array {
                $selected = $selectedByUser->get($user->id);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'assigned' => $selected !== null,
                    'is_default' => (bool) ($selected?->pivot?->is_default ?? false),
                ];
            })
            ->values();

        return Inertia::render('Inventory/Warehouses/AssignUsers', [
            'warehouse' => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'city' => $warehouse->city,
            ],
            'users' => $users,
        ]);
    }

    public function assignUsers(AssignUsersRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $this->authorize('update', $warehouse);

        $validated = $request->validated();
        $userItems = collect($validated['users'])
            ->map(function (array $item): array {
                return [
                    'user_id' => (int) $item['user_id'],
                    'is_default' => (bool) ($item['is_default'] ?? false),
                ];
            })
            ->unique('user_id')
            ->values();

        DB::transaction(function () use ($warehouse, $userItems): void {
            $syncData = [];
            foreach ($userItems as $item) {
                $syncData[$item['user_id']] = [
                    'is_default' => $item['is_default'],
                ];
            }

            $warehouse->users()->sync($syncData);

            $defaultUserIds = $userItems
                ->filter(fn (array $item): bool => $item['is_default'])
                ->pluck('user_id')
                ->all();

            if (! empty($defaultUserIds)) {
                DB::table('warehouse_user')
                    ->whereIn('user_id', $defaultUserIds)
                    ->where('warehouse_id', '!=', $warehouse->id)
                    ->update([
                        'is_default' => false,
                        'updated_at' => now(),
                    ]);
            }
        });

        return redirect()
            ->route('warehouses.show', $warehouse)
            ->with('success', __('Usuarios asignados correctamente.'));
    }

    public function setCurrentWarehouse(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['bail', 'required', 'integer', 'exists:warehouses,id'],
        ]);

        $user = $request->user();
        $warehouse = Warehouse::query()->findOrFail($validated['warehouse_id']);

        $this->authorize('view', $warehouse);

        $currentWarehouse = $this->warehouseContextService->resolveCurrentWarehouse($user, (int) $warehouse->id);
        if (! $currentWarehouse) {
            return back()->with('error', __('No tienes acceso a la bodega seleccionada.'));
        }

        $request->session()->put('current_warehouse_id', $currentWarehouse->id);

        return back()->with('success', __('Bodega activa actualizada.'));
    }
}
