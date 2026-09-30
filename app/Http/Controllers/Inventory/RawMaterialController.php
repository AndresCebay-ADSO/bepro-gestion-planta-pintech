<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Actions\Shared\DeleteUnusedRecordAction;
use App\Enums\Permission;
use App\Filters\RawMaterialFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\RawMaterials\IndexRawMaterialRequest;
use App\Http\Requests\RawMaterials\StoreRawMaterialRequest;
use App\Http\Requests\RawMaterials\UpdateRawMaterialRequest;
use App\Jobs\RecalculateRawMaterialDependentCosts;
use App\Models\InventoryBatch;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use App\Models\UnitOfMeasure;
use App\Services\DecimalCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RawMaterialController extends Controller
{
    public function __construct(
        private readonly DeleteUnusedRecordAction $deleteUnused,
        private readonly DecimalCalculator $calculator,
    ) {}

    /**
     * Display a listing of the raw materials.
     */
    public function index(IndexRawMaterialRequest $request): Response
    {
        $user = $request->user();
        $canViewCosts = $user?->can(Permission::CostsView->value) ?? false;

        $rawMaterials = (new RawMaterialFilter($request))
            ->apply(RawMaterial::query())
            ->with([
                'category:id,name,type',
                'unitOfMeasure:id,name,symbol',
            ])
            ->withSum('inventoryBatches as available_stock', 'remaining_quantity')
            ->withExists(['inventoryBatches as has_batches'])
            ->withExists(['inventoryMovements as has_movements'])
            ->withExists(['formulaDetails as has_formulas'])
            ->withExists(['productionOrderDetails as has_orders'])
            ->withExists(['packagedVariants as has_variants', 'labeledVariants as has_labeled_variants'])
            ->withExists(['lineAdjustments as has_adjustments'])
            ->withCount(['alerts as active_alerts_count' => fn ($query) => $query->where('is_resolved', false)])
            ->withExists(['alerts as has_critical_alert' => fn ($query) => $query
                ->where('is_resolved', false)
                ->where('severity', 'alta')])
            ->latest('id')
            ->paginate(15)
            ->onEachSide(1)
            ->withQueryString()
            ->through(function (RawMaterial $rawMaterial) use ($user, $canViewCosts): array {
                $hasActivity = (bool) ($rawMaterial->has_batches
                    || $rawMaterial->has_movements
                    || $rawMaterial->has_formulas
                    || $rawMaterial->has_orders
                    || $rawMaterial->has_variants
                    || $rawMaterial->has_labeled_variants
                    || $rawMaterial->has_adjustments);

                return [
                    'id' => $rawMaterial->id,
                    'code' => $rawMaterial->code,
                    'current_price' => $canViewCosts ? $rawMaterial->current_price : null,
                    'previous_price' => $canViewCosts ? $rawMaterial->previous_price : null,
                    'minimum_stock' => $rawMaterial->minimum_stock,
                    'available_stock' => $rawMaterial->available_stock ?? 0,
                    'has_available_stock' => (float) ($rawMaterial->available_stock ?? 0) > 0,
                    'has_activity' => $hasActivity,
                    'alert_days_before_expiry' => $rawMaterial->alert_days_before_expiry,
                    'active_alerts_count' => (int) ($rawMaterial->active_alerts_count ?? 0),
                    'has_critical_alert' => (bool) ($rawMaterial->has_critical_alert ?? false),
                    'is_active' => $rawMaterial->is_active,
                    'category' => $rawMaterial->category ? [
                        'id' => $rawMaterial->category->id,
                        'name' => $rawMaterial->category->name,
                        'type_label' => $rawMaterial->category->type->label(),
                    ] : null,
                    'unit_of_measure' => $rawMaterial->unitOfMeasure ? [
                        'id' => $rawMaterial->unitOfMeasure->id,
                        'name' => $rawMaterial->unitOfMeasure->name,
                        'symbol' => $rawMaterial->unitOfMeasure->symbol,
                    ] : null,
                    'can' => [
                        'view' => Gate::forUser($user)->allows('view', $rawMaterial),
                        'update' => Gate::forUser($user)->allows('update', $rawMaterial),
                        // Activa: se desactiva o se elimina. Inactiva: solo se puede eliminar, si nunca se usó.
                        'delete' => $rawMaterial->is_active
                            ? Gate::forUser($user)->allows('deactivate', $rawMaterial)
                            : Gate::forUser($user)->allows('delete', $rawMaterial) && ! $hasActivity,
                        'reactivate' => Gate::forUser($user)->allows('reactivate', $rawMaterial) && ! $rawMaterial->is_active,
                    ],
                ];
            });

        return Inertia::render('Inventory/RawMaterials/Index', [
            'rawMaterials' => $rawMaterials,
            'filters' => $request->validated(),
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
            'can' => ['updateCosts' => Gate::allows(Permission::CostsUpdate->value)],
            'categories' => $this->categoryOptions(RawMaterialCategory::query()->selectable()),
            'units' => UnitOfMeasure::query()
                ->selectable()
                ->select('id', 'name', 'symbol')
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

    /**
     * Display the specified raw material with its batches and activity status.
     */
    public function show(Request $request, RawMaterial $rawMaterial): Response
    {
        $this->authorize('view', $rawMaterial);

        $canViewCosts = $request->user()?->can(Permission::CostsView->value) ?? false;

        $rawMaterial->load([
            'category:id,name,code,type',
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
        ])->loadExists([
            'inventoryMovements as has_movements',
            'formulaDetails as has_formulas',
            'productionOrderDetails as has_orders',
            'packagedVariants as has_variants',
            'labeledVariants as has_labeled_variants',
            'lineAdjustments as has_adjustments',
        ]);

        $hasAvailableStock = $rawMaterial->inventoryBatches
            ->contains(fn ($batch) => (float) $batch->remaining_quantity > 0);

        $hasActivity = (bool) ($rawMaterial->inventoryBatches->isNotEmpty()
            || $rawMaterial->has_movements
            || $rawMaterial->has_formulas
            || $rawMaterial->has_orders
            || $rawMaterial->has_variants
            || $rawMaterial->has_labeled_variants
            || $rawMaterial->has_adjustments);

        return Inertia::render('Inventory/RawMaterials/Show', [
            'returnTo' => $this->resolveReturnTo($request),
            // Array explícito: en materias primas el precio actual, el anterior y el de cada lote son costo
            // (docs/MATRIZ_RBAC.md, principio 1).
            'rawMaterial' => [
                'id' => $rawMaterial->id,
                'code' => $rawMaterial->code,
                ...($canViewCosts ? [
                    'current_price' => $rawMaterial->current_price,
                    'previous_price' => $rawMaterial->previous_price,
                ] : []),
                'minimum_stock' => $rawMaterial->minimum_stock,
                'alert_days_before_expiry' => $rawMaterial->alert_days_before_expiry,
                'tracks_inventory' => $rawMaterial->tracks_inventory,
                'is_active' => $rawMaterial->is_active,
                'category' => $rawMaterial->category ? [
                    'id' => $rawMaterial->category->id,
                    'name' => $rawMaterial->category->name,
                    'code' => $rawMaterial->category->code,
                    'type' => $rawMaterial->category->type->value,
                    'type_label' => $rawMaterial->category->type->label(),
                ] : null,
                'unit_of_measure' => $rawMaterial->unitOfMeasure ? [
                    'id' => $rawMaterial->unitOfMeasure->id,
                    'name' => $rawMaterial->unitOfMeasure->name,
                    'symbol' => $rawMaterial->unitOfMeasure->symbol,
                ] : null,
                'inventory_batches' => $rawMaterial->inventoryBatches->map(fn (InventoryBatch $batch): array => [
                    'id' => $batch->id,
                    'lot_number' => $batch->lot_number,
                    'supplier' => $batch->supplier,
                    'initial_quantity' => $batch->initial_quantity,
                    'remaining_quantity' => $batch->remaining_quantity,
                    ...($canViewCosts ? ['unit_price' => $batch->unit_price] : []),
                    'entry_date' => $batch->entry_date?->format('Y-m-d'),
                    'expiry_date' => $batch->expiry_date?->format('Y-m-d'),
                ])->values(),
            ],
            'hasAvailableStock' => $hasAvailableStock,
            'hasActivity' => $hasActivity,
            'can' => [
                'update' => Gate::allows('update', $rawMaterial),
                'delete' => $rawMaterial->is_active
                    ? Gate::allows('deactivate', $rawMaterial)
                    : Gate::allows('delete', $rawMaterial) && ! $hasActivity,
                'reactivate' => Gate::allows('reactivate', $rawMaterial) && ! $rawMaterial->is_active,
                'viewCosts' => $canViewCosts,
            ],
        ]);
    }

    public function edit(RawMaterial $rawMaterial): Response
    {
        $this->authorize('update', $rawMaterial);

        $canUpdateCosts = Gate::allows(Permission::CostsUpdate->value);

        return Inertia::render('Inventory/RawMaterials/Edit', [
            // Solo los campos del formulario. El precio es costo: solo viaja a quien puede fijarlo, y el formulario solo lo
            // edita si la materia prima no controla inventario.
            'rawMaterial' => [
                'id' => $rawMaterial->id,
                'code' => $rawMaterial->code,
                'category_id' => $rawMaterial->category_id,
                'unit_of_measure_id' => $rawMaterial->unit_of_measure_id,
                'minimum_stock' => $rawMaterial->minimum_stock,
                'alert_days_before_expiry' => $rawMaterial->alert_days_before_expiry,
                'price_variation_threshold' => $rawMaterial->price_variation_threshold,
                'tracks_inventory' => $rawMaterial->tracks_inventory,
                ...($canUpdateCosts ? ['current_price' => $rawMaterial->current_price] : []),
                'is_active' => $rawMaterial->is_active,
            ],
            'can' => ['updateCosts' => $canUpdateCosts],
            'categories' => $this->categoryOptions(RawMaterialCategory::query()->selectable([$rawMaterial->category_id])),
            'units' => UnitOfMeasure::query()
                ->selectable([$rawMaterial->unit_of_measure_id])
                ->select('id', 'name', 'symbol')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(UpdateRawMaterialRequest $request, RawMaterial $rawMaterial): RedirectResponse
    {
        $this->authorize('update', $rawMaterial);

        $validated = $request->validated();

        // Precio escrito a mano (solo sin control de inventario): se guarda el anterior, como al recalcularlo desde las
        // compras, y se recalculan en segundo plano los productos y presentaciones que lo usan.
        $priceChanged = array_key_exists('current_price', $validated)
            && ! $this->samePrice($rawMaterial->current_price, $validated['current_price']);

        if ($priceChanged) {
            $validated['previous_price'] = $rawMaterial->current_price;
        }

        $rawMaterial->update($validated);

        if ($priceChanged) {
            RecalculateRawMaterialDependentCosts::dispatch((int) $rawMaterial->id);
        }

        return redirect()
            ->route('raw-materials.index')
            ->with('success', __('Materia prima actualizada exitosamente.'));
    }

    private function samePrice(?string $current, mixed $new): bool
    {
        if ($current === null || $new === null || $new === '') {
            return $current === null && ($new === null || $new === '');
        }

        return $this->calculator->cmp($current, (string) $new, 4) === 0;
    }

    /**
     * Remove or deactivate the specified raw material depending on activity and stock.
     */
    public function destroy(Request $request, RawMaterial $rawMaterial): RedirectResponse
    {
        $this->authorize('deactivate', $rawMaterial);

        // El borrado físico exige además raw_materials.delete (SuperAdmin); si no, se desactiva.
        $canDeletePermanently = $request->user()?->can('delete', $rawMaterial) ?? false;

        return DB::transaction(function () use ($rawMaterial, $canDeletePermanently): RedirectResponse {
            /** @var RawMaterial $lockedRawMaterial */
            $lockedRawMaterial = RawMaterial::query()
                ->lockForUpdate()
                ->findOrFail($rawMaterial->id);

            // Las claves foráneas deciden si tiene historial (docs/POLITICA_ELIMINACION.md §4); si lo tiene, se desactiva.
            if ($canDeletePermanently && $this->deleteUnused->execute($lockedRawMaterial)) {
                return redirect()
                    ->route('raw-materials.index')
                    ->with('success', __('Materia prima eliminada físicamente exitosamente.'));
            }

            if (! $lockedRawMaterial->is_active) {
                return back()->with('error', $canDeletePermanently
                    ? __('La materia prima tiene historial: no se puede eliminar y ya se encuentra inactiva.')
                    : __('La materia prima ya se encuentra inactiva.'));
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
        $this->authorize('reactivate', $rawMaterial);

        if ($rawMaterial->is_active) {
            return back()->with('error', __('La materia prima ya se encuentra activa.'));
        }

        $rawMaterial->update(['is_active' => true]);

        return redirect()
            ->route('raw-materials.index', ['status' => 'inactive'])
            ->with('success', __('Materia prima reactivada exitosamente.'));
    }

    /**
     * Categorías para el selector, con el tipo de insumo que heredará la materia prima: el formulario lo muestra en
     * cuanto se elige la categoría.
     *
     * @param  Builder<RawMaterialCategory>  $query
     * @return list<array{id: int, name: string, code: string, type: string, type_label: string}>
     */
    private function categoryOptions(Builder $query): array
    {
        return $query
            ->select('id', 'name', 'code', 'type')
            ->orderBy('name')
            ->get()
            ->map(fn (RawMaterialCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'code' => $category->code,
                'type' => $category->type->value,
                'type_label' => $category->type->label(),
            ])
            ->all();
    }
}
