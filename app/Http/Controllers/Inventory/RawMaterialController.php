<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\RawMaterials\StoreRawMaterialRequest;
use App\Http\Requests\RawMaterials\UpdateRawMaterialRequest;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use App\Models\UnitOfMeasure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RawMaterialController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', RawMaterial::class);

        $search = strtolower(trim((string) $request->input('search')));
        $user = $request->user();

        $rawMaterials = RawMaterial::query()
            ->with(['category:id,name', 'unitOfMeasure:id,name,symbol'])
            ->when($search !== '', fn ($query) => $query->whereRaw('LOWER(code) LIKE ?', ["%{$search}%"]))
            ->latest('id')
            ->paginate(15)
            ->onEachSide(1)
            ->withQueryString()
            ->through(function (RawMaterial $rawMaterial) use ($user): array {
                return [
                    'id' => $rawMaterial->id,
                    'code' => $rawMaterial->code,
                    'current_price' => $rawMaterial->current_price,
                    'previous_price' => $rawMaterial->previous_price,
                    'minimum_stock' => $rawMaterial->minimum_stock,
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
                    ],
                ];
            });

        return Inertia::render('Inventory/RawMaterials/Index', [
            'rawMaterials' => $rawMaterials,
            'filters' => [
                'search' => $search,
            ],
            'can' => [
                'create' => Gate::allows('create', RawMaterial::class),
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

        $rawMaterial->update($request->validated());

        return redirect()
            ->route('raw-materials.index')
            ->with('success', __('Materia prima actualizada exitosamente.'));
    }

    public function destroy(RawMaterial $rawMaterial): RedirectResponse
    {
        $this->authorize('delete', $rawMaterial);

        $hasAvailableBatches = $rawMaterial->inventoryBatches()
            ->where('remaining_quantity', '>', 0)
            ->exists();

        if ($hasAvailableBatches) {
            return back()->with('error', __('No se puede eliminar la materia prima porque tiene lotes activos con stock disponible.'));
        }

        $rawMaterial->delete();

        return redirect()
            ->route('raw-materials.index')
            ->with('success', __('Materia prima eliminada exitosamente.'));
    }
}
