<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings\Catalogs;

use App\Actions\Shared\DeleteUnusedRecordAction;
use App\Filters\ProductCategoryFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogs\IndexProductCategoryRequest;
use App\Http\Requests\Catalogs\StoreProductCategoryRequest;
use App\Http\Requests\Catalogs\UpdateProductCategoryRequest;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catálogo de categorías de producto, en Configuración → Catálogos.
 */
class ProductCategoryController extends Controller
{
    public function __construct(
        private readonly DeleteUnusedRecordAction $deleteUnused,
    ) {}

    public function index(IndexProductCategoryRequest $request): Response
    {
        $categories = (new ProductCategoryFilter($request))
            ->apply(ProductCategory::query()->withCount('products'))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->onEachSide(1)
            ->withQueryString()
            ->through(fn (ProductCategory $category): array => [
                ...$this->categoryData($category),
                'can' => [
                    'update' => Gate::allows('update', $category),
                    'delete' => Gate::allows('delete', $category),
                ],
            ]);

        return Inertia::render('Settings/Catalogs/ProductCategories/Index', [
            'categories' => $categories,
            'filters' => $request->validated(),
            'can' => [
                'create' => Gate::allows('create', ProductCategory::class),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', ProductCategory::class);

        return Inertia::render('Settings/Catalogs/ProductCategories/Create');
    }

    public function store(StoreProductCategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', ProductCategory::class);

        ProductCategory::create($request->validated());

        return redirect()
            ->route('catalogs.product-categories.index')
            ->with('success', __('Categoría de producto registrada exitosamente.'));
    }

    public function edit(ProductCategory $productCategory): Response
    {
        $this->authorize('update', $productCategory);

        $productCategory->loadCount('products');

        return Inertia::render('Settings/Catalogs/ProductCategories/Edit', [
            'category' => $this->categoryData($productCategory),
        ]);
    }

    public function update(UpdateProductCategoryRequest $request, ProductCategory $productCategory): RedirectResponse
    {
        $this->authorize('update', $productCategory);

        $productCategory->update($request->validated());

        return redirect()
            ->route('catalogs.product-categories.index')
            ->with('success', __('Categoría de producto actualizada exitosamente.'));
    }

    public function destroy(ProductCategory $productCategory): RedirectResponse
    {
        $this->authorize('delete', $productCategory);

        // Solo si nunca se usó (docs/POLITICA_ELIMINACION.md): la clave foránea RESTRICT decide.
        if (! $this->deleteUnused->execute($productCategory)) {
            return back()->with('error', __('La categoría tiene productos. Desactívala en su lugar.'));
        }

        return redirect()
            ->route('catalogs.product-categories.index')
            ->with('success', __('Categoría de producto eliminada exitosamente.'));
    }

    /**
     * Requiere el conteo `products_count` cargado.
     *
     * @return array<string, mixed>
     */
    private function categoryData(ProductCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'description' => $category->description,
            'is_active' => $category->is_active,
            'products_count' => (int) $category->products_count,
        ];
    }
}
