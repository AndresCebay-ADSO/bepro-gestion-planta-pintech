<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings\Catalogs;

use App\Actions\Shared\DeleteUnusedRecordAction;
use App\Filters\UnitOfMeasureFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogs\IndexUnitOfMeasureRequest;
use App\Http\Requests\Catalogs\StoreUnitOfMeasureRequest;
use App\Http\Requests\Catalogs\UpdateUnitOfMeasureRequest;
use App\Models\UnitOfMeasure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catálogo de unidades de medida, en Configuración → Catálogos.
 */
class UnitOfMeasureController extends Controller
{
    public function __construct(
        private readonly DeleteUnusedRecordAction $deleteUnused,
    ) {}

    public function index(IndexUnitOfMeasureRequest $request): Response
    {
        $units = (new UnitOfMeasureFilter($request))
            ->apply(UnitOfMeasure::query()->withUsageCounts())
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->onEachSide(1)
            ->withQueryString()
            ->through(fn (UnitOfMeasure $unit): array => [
                ...$this->unitData($unit),
                'can' => [
                    'update' => Gate::allows('update', $unit),
                    'delete' => Gate::allows('delete', $unit),
                ],
            ]);

        return Inertia::render('Settings/Catalogs/UnitsOfMeasure/Index', [
            'units' => $units,
            'filters' => $request->validated(),
            'can' => [
                'create' => Gate::allows('create', UnitOfMeasure::class),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', UnitOfMeasure::class);

        return Inertia::render('Settings/Catalogs/UnitsOfMeasure/Create');
    }

    public function store(StoreUnitOfMeasureRequest $request): RedirectResponse
    {
        $this->authorize('create', UnitOfMeasure::class);

        UnitOfMeasure::create($request->validated());

        return redirect()
            ->route('catalogs.units-of-measure.index')
            ->with('success', __('Unidad de medida registrada exitosamente.'));
    }

    public function edit(UnitOfMeasure $unitOfMeasure): Response
    {
        $this->authorize('update', $unitOfMeasure);

        $unitOfMeasure->loadCount(UnitOfMeasure::USAGE_RELATIONS);

        return Inertia::render('Settings/Catalogs/UnitsOfMeasure/Edit', [
            'unit' => [
                ...$this->unitData($unitOfMeasure),
                'description' => $unitOfMeasure->description,
            ],
        ]);
    }

    public function update(UpdateUnitOfMeasureRequest $request, UnitOfMeasure $unitOfMeasure): RedirectResponse
    {
        $this->authorize('update', $unitOfMeasure);

        $unitOfMeasure->update($request->safe()->except('confirm_factor_change'));

        return redirect()
            ->route('catalogs.units-of-measure.index')
            ->with('success', __('Unidad de medida actualizada exitosamente.'));
    }

    public function destroy(UnitOfMeasure $unitOfMeasure): RedirectResponse
    {
        $this->authorize('delete', $unitOfMeasure);

        // Solo si nunca se usó (docs/POLITICA_ELIMINACION.md): las claves foráneas RESTRICT deciden.
        if (! $this->deleteUnused->execute($unitOfMeasure)) {
            return back()->with('error', __('La unidad de medida está en uso (materias primas, productos, presentaciones o fórmulas). Desactívala en su lugar.'));
        }

        return redirect()
            ->route('catalogs.units-of-measure.index')
            ->with('success', __('Unidad de medida eliminada exitosamente.'));
    }

    /**
     * Requiere los conteos de uso cargados (`withUsageCounts()` o `loadCount()`).
     *
     * @return array<string, mixed>
     */
    private function unitData(UnitOfMeasure $unit): array
    {
        $usage = [
            'raw_materials' => (int) $unit->raw_materials_count,
            'products' => (int) $unit->products_count,
            'product_variants' => (int) $unit->product_variants_count,
            'formula_details' => (int) $unit->formula_details_count,
        ];

        return [
            'id' => $unit->id,
            'code' => $unit->code,
            'name' => $unit->name,
            'symbol' => $unit->symbol,
            'to_kg_conversion' => $unit->to_kg_conversion,
            'to_liter_conversion' => $unit->to_liter_conversion,
            'is_active' => $unit->is_active,
            'usage' => $usage,
            'in_use' => array_sum($usage) > 0,
            // Solo las líneas de fórmula y las materias primas convierten con la equivalencia de la unidad.
            'affects_conversions' => $usage['raw_materials'] + $usage['formula_details'] > 0,
        ];
    }
}
