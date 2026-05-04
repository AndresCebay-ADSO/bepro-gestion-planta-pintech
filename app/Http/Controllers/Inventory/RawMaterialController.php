<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\RawMaterials\StoreRawMaterialRequest;
use App\Http\Requests\RawMaterials\UpdateRawMaterialRequest;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use App\Models\UnitOfMeasure;
use App\Services\ProductionCostRecalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RawMaterialController extends Controller
{
    public function __construct(
        private readonly ProductionCostRecalculationService $productionCostRecalculationService
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', RawMaterial::class);

        $search = strtolower(trim((string) $request->input('search')));
        $user = $request->user();
        $status = $request->string('status')->toString();
        $canViewCosts = $user?->hasAnyRole(['admin', 'produccion']) ?? false;

        $rawMaterials = RawMaterial::query()
            ->with(['category:id,name', 'unitOfMeasure:id,name,symbol'])
            ->withSum('inventoryBatches as available_stock', 'remaining_quantity')
            ->when($status === '' || $status === 'active', fn ($query) => $query->active())
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($search !== '', fn ($query) => $query->whereRaw('LOWER(code) LIKE ?', ["%{$search}%"]))
            ->latest('id')
            ->paginate(15)
            ->onEachSide(1)
            ->withQueryString()
            ->through(function (RawMaterial $rawMaterial) use ($user, $canViewCosts): array {
                return [
                    'id' => $rawMaterial->id,
                    'code' => $rawMaterial->code,
                    'current_price' => $canViewCosts ? $rawMaterial->current_price : null,
                    'previous_price' => $canViewCosts ? $rawMaterial->previous_price : null,
                    'minimum_stock' => $rawMaterial->minimum_stock,
                    'available_stock' => $rawMaterial->available_stock ?? 0,
                    'alert_days_before_expiry' => $rawMaterial->alert_days_before_expiry,
                    'is_active' => $rawMaterial->is_active,
                    'category' => $rawMaterial->category ? [
                        'id' => $rawMaterial->category->id,
                        'name' => $rawMaterial->category->name,
                    ] : null,
                    'unit_of_measure' => $rawMaterial->unitOfMeasure ? [
                        'id' => $rawMaterial->unitOfMeasure->id,
                        'name' => $rawMaterial->unitOfMeasure->name,
                        'symbol' => $rawMaterial->unitOfMeasure->symbol,
                    ] : null,
                    'can' => [
                        'view' => Gate::forUser($user)->allows('view', $rawMaterial),
                        'update' => Gate::forUser($user)->allows('update', $rawMaterial),
                        'delete' => Gate::forUser($user)->allows('delete', $rawMaterial),
                        'reactivate' => Gate::forUser($user)->allows('update', $rawMaterial) && ! $rawMaterial->is_active,
                    ],
                ];
            });

        return Inertia::render('Inventory/RawMaterials/Index', [
            'rawMaterials' => $rawMaterials,
            'filters' => [
                'search' => $search,
                'status' => $status === '' ? 'active' : $status,
            ],
            'can' => [
                'create' => Gate::allows('create', RawMaterial::class),
                'view_costs' => $canViewCosts,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', RawMaterial::class);

        return Inertia::render('Inventory/RawMaterials/Create', [
            'categories' => RawMaterialCategory::query()
                ->select('id', 'name', 'code')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'units' => UnitOfMeasure::query()
                ->select('id', 'name', 'symbol')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreRawMaterialRequest $request): RedirectResponse
    {
        $this->authorize('create', RawMaterial::class);

        RawMaterial::create($request->validated());

        return redirect()
            ->route('raw-materials.index')
            ->with('success', __('Materia prima registrada exitosamente.'));
    }

    public function show(RawMaterial $rawMaterial): Response
    {
        $this->authorize('view', $rawMaterial);

        return Inertia::render('Inventory/RawMaterials/Show', [
            'rawMaterial' => $rawMaterial->load([
                'category:id,name,code',
                'unitOfMeasure:id,name,symbol',
                'inventoryBatches' => fn ($query) => $query
                    ->select(
                        'id',
                        'raw_material_id',
                        'lot_number',
                        'supplier',
                        'initial_quantity',
                        'remaining_quantity',
                        'unit_price',
                        'entry_date',
                        'expiry_date'
                    )
                    ->orderByDesc('entry_date')
                    ->orderByDesc('id'),
            ]),
            'can' => [
                'update' => Gate::allows('update', $rawMaterial),
                'delete' => Gate::allows('delete', $rawMaterial),
                'reactivate' => Gate::allows('update', $rawMaterial) && ! $rawMaterial->is_active,
            ],
        ]);
    }

    public function edit(RawMaterial $rawMaterial): Response
    {
        $this->authorize('update', $rawMaterial);

        return Inertia::render('Inventory/RawMaterials/Edit', [
            'rawMaterial' => $rawMaterial,
            'categories' => RawMaterialCategory::query()
                ->select('id', 'name', 'code')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'units' => UnitOfMeasure::query()
                ->select('id', 'name', 'symbol')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(UpdateRawMaterialRequest $request, RawMaterial $rawMaterial): RedirectResponse
    {
        $this->authorize('update', $rawMaterial);

        $validated = $request->validated();
        $currentPriceChanged = isset($validated['current_price'])
            && (string) $rawMaterial->current_price !== (string) $validated['current_price'];

        if ($currentPriceChanged && ! array_key_exists('previous_price', $validated)) {
            $validated['previous_price'] = $rawMaterial->current_price;
        }

        $rawMaterial->update($validated);

        if ($currentPriceChanged) {
            $this->productionCostRecalculationService->recalculateForRawMaterial((int) $rawMaterial->id);
        }

        return redirect()
            ->route('raw-materials.index')
            ->with('success', __('Materia prima actualizada exitosamente.'));
    }

    public function destroy(RawMaterial $rawMaterial): RedirectResponse
    {
        $this->authorize('delete', $rawMaterial);

        return DB::transaction(function () use ($rawMaterial): RedirectResponse {
            /** @var RawMaterial $lockedRawMaterial */
            $lockedRawMaterial = RawMaterial::query()
                ->lockForUpdate()
                ->findOrFail($rawMaterial->id);

            if (! $lockedRawMaterial->is_active) {
                return back()->with('error', __('La materia prima ya se encuentra inactiva.'));
            }

            $hasActivity = $lockedRawMaterial->inventoryBatches()->exists()
                || $lockedRawMaterial->inventoryMovements()->exists()
                || $lockedRawMaterial->formulaDetails()->exists()
                || $lockedRawMaterial->productionOrderDetails()->exists();

            if (! $hasActivity) {
                $lockedRawMaterial->delete();

                return redirect()
                    ->route('raw-materials.index')
                    ->with('success', __('Materia prima eliminada físicamente exitosamente.'));
            }

            $hasAvailableBatches = $lockedRawMaterial->inventoryBatches()
                ->where('remaining_quantity', '>', 0)
                ->exists();

            if ($hasAvailableBatches) {
                return back()->with('error', __('No se puede desactivar ni eliminar la materia prima porque tiene lotes activos con stock disponible.'));
            }

            $lockedRawMaterial->update(['is_active' => false]);

            return redirect()
                ->route('raw-materials.index')
                ->with('success', __('Materia prima desactivada exitosamente (conserva historial).'));
        }, attempts: 3);
    }

    public function reactivate(RawMaterial $rawMaterial): RedirectResponse
    {
        $this->authorize('update', $rawMaterial);

        if ($rawMaterial->is_active) {
            return back()->with('error', __('La materia prima ya se encuentra activa.'));
        }

        $rawMaterial->update(['is_active' => true]);

        return redirect()
            ->route('raw-materials.index', ['status' => 'inactive'])
            ->with('success', __('Materia prima reactivada exitosamente.'));
    }
}
