<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings\Catalogs;

use App\Actions\Shared\DeleteUnusedRecordAction;
use App\Enums\RawMaterialType;
use App\Filters\RawMaterialCategoryFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogs\IndexRawMaterialCategoryRequest;
use App\Http\Requests\Catalogs\StoreRawMaterialCategoryRequest;
use App\Http\Requests\Catalogs\UpdateRawMaterialCategoryRequest;
use App\Models\RawMaterialCategory;
use App\Support\EnumOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catálogo de categorías de materia prima, en Configuración → Catálogos. Cada categoría tiene un tipo de insumo
 * (App\Enums\RawMaterialType) que decide dónde se ofrecen sus materias primas.
 */
class RawMaterialCategoryController extends Controller
{
    public function __construct(
        private readonly DeleteUnusedRecordAction $deleteUnused,
    ) {}

    public function index(IndexRawMaterialCategoryRequest $request): Response
    {
        $categories = (new RawMaterialCategoryFilter($request))
            ->apply(RawMaterialCategory::query()->withCount('rawMaterials'))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->onEachSide(1)
            ->withQueryString()
            ->through(fn (RawMaterialCategory $category): array => [
                ...$this->categoryData($category),
                'can' => [
                    'update' => Gate::allows('update', $category),
                    'delete' => Gate::allows('delete', $category),
                ],
            ]);

        return Inertia::render('Settings/Catalogs/RawMaterialCategories/Index', [
            'categories' => $categories,
            'filters' => $request->validated(),
            'typeOptions' => EnumOptions::for(RawMaterialType::cases()),
            'can' => [
                'create' => Gate::allows('create', RawMaterialCategory::class),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', RawMaterialCategory::class);

        return Inertia::render('Settings/Catalogs/RawMaterialCategories/Create', [
            'typeOptions' => EnumOptions::for(RawMaterialType::cases()),
        ]);
    }

    public function store(StoreRawMaterialCategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', RawMaterialCategory::class);

        RawMaterialCategory::create($request->validated());

        return redirect()
            ->route('catalogs.raw-material-categories.index')
            ->with('success', __('Categoría de materia prima registrada exitosamente.'));
    }

    public function edit(RawMaterialCategory $rawMaterialCategory): Response
    {
        $this->authorize('update', $rawMaterialCategory);

        $rawMaterialCategory->loadCount('rawMaterials');

        return Inertia::render('Settings/Catalogs/RawMaterialCategories/Edit', [
            'category' => [
                ...$this->categoryData($rawMaterialCategory),
                'description' => $rawMaterialCategory->description,
            ],
            'typeOptions' => EnumOptions::for(RawMaterialType::cases()),
        ]);
    }

    public function update(UpdateRawMaterialCategoryRequest $request, RawMaterialCategory $rawMaterialCategory): RedirectResponse
    {
        $this->authorize('update', $rawMaterialCategory);

        $rawMaterialCategory->update($request->validated());

        return redirect()
            ->route('catalogs.raw-material-categories.index')
            ->with('success', __('Categoría de materia prima actualizada exitosamente.'));
    }

    public function destroy(RawMaterialCategory $rawMaterialCategory): RedirectResponse
    {
        $this->authorize('delete', $rawMaterialCategory);

        // Solo si nunca se usó (docs/POLITICA_ELIMINACION.md): la clave foránea RESTRICT decide.
        if (! $this->deleteUnused->execute($rawMaterialCategory)) {
            return back()->with('error', __('La categoría tiene materias primas. Desactívala en su lugar.'));
        }

        return redirect()
            ->route('catalogs.raw-material-categories.index')
            ->with('success', __('Categoría de materia prima eliminada exitosamente.'));
    }

    /**
     * Requiere el conteo `raw_materials_count` cargado.
     *
     * @return array<string, mixed>
     */
    private function categoryData(RawMaterialCategory $category): array
    {
        return [
            'id' => $category->id,
            'code' => $category->code,
            'name' => $category->name,
            'type' => $category->type->value,
            'type_label' => $category->type->label(),
            'is_active' => $category->is_active,
            'raw_materials_count' => (int) $category->raw_materials_count,
        ];
    }
}
