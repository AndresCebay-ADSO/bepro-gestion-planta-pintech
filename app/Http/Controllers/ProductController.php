<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\QrDocumentType;
use App\Filters\ProductFilter;
use App\Http\Requests\Products\IndexProductRequest;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Services\DecimalCalculator;
use App\Services\FinishedInventoryQueryService;
use App\Services\ProductionCostRecalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * Atributos que revelan el costo. Solo con costs.view.
     * current_price es el precio interno (costo × (1 + CIF %)), no el de venta; el CIF, el umbral y el margen lo derivan.
     */
    private const PRODUCT_COST_ATTRIBUTES = ['current_cost', 'current_price', 'cif_percentage', 'price_threshold', 'sales_margin'];

    private const VARIANT_COST_ATTRIBUTES = ['current_cost', 'current_price'];

    public function __construct(
        private readonly ProductionCostRecalculationService $productionCostRecalculationService,
        private readonly DecimalCalculator $calculator,
        private readonly FinishedInventoryQueryService $finishedInventoryQueryService,
    ) {}

    public function index(IndexProductRequest $request): Response
    {
        $canViewCosts = $request->user()?->can(Permission::CostsView->value) ?? false;

        $products = (new ProductFilter($request))
            ->apply(Product::query())
            ->with(['category:id,name', 'unitOfMeasure:id,name,symbol'])
            ->latest('id')
            ->paginate(15)
            ->onEachSide(1)
            ->withQueryString()
            ->through(fn (Product $product) => [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'is_active' => $product->is_active,
                ...($canViewCosts ? ['current_price' => $product->current_price] : []),
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                ] : null,
                'unit_of_measure' => $product->unitOfMeasure ? [
                    'id' => $product->unitOfMeasure->id,
                    'name' => $product->unitOfMeasure->name,
                    'symbol' => $product->unitOfMeasure->symbol,
                ] : null,
                'can' => [
                    'view' => Gate::allows('view', $product),
                    'update' => Gate::allows('update', $product),
                    'delete' => Gate::allows('delete', $product),
                ],
            ]);

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => $request->validated(),
            'can' => [
                'create' => Gate::allows('create', Product::class),
                'managePrices' => Gate::allows(Permission::CostsUpdate->value),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Product::class);

        return Inertia::render('Products/Create', [
            'categories' => ProductCategory::query()->select('id', 'name')->orderBy('name')->get(),
            'units' => UnitOfMeasure::query()->select('id', 'name', 'symbol')->orderBy('name')->get(),
            'can' => [
                'managePrices' => Gate::allows(Permission::CostsUpdate->value),
            ],
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $validated = $request->validated();

        if (! Gate::allows(Permission::CostsUpdate->value)) {
            $validated['cif_percentage'] = '0';
            $validated['price_threshold'] = '0';
        }

        Product::create($validated);

        return redirect()->route('products.index')->with('success', __('Producto creado exitosamente.'));
    }

    public function show(Request $request, Product $product): Response
    {
        $this->authorize('view', $product);

        $user = $request->user();
        $canViewCosts = $user?->can(Permission::CostsView->value) ?? false;
        $canViewFormulas = $user?->can(Permission::FormulasView->value) ?? false;

        return Inertia::render('Products/Show', [
            'returnTo' => $this->resolveReturnTo($request),
            'finishedInventory' => $user !== null
                ? $this->finishedInventoryQueryService->inventoryRowsForProduct($user, $product)
                : [],
            'product' => $this->productForShow($product, $canViewCosts, $canViewFormulas),
            'can' => [
                'update' => Gate::allows('update', $product),
                'delete' => Gate::allows('delete', $product),
                'manageVariants' => Gate::allows('manageVariants', $product),
                'manageDocuments' => Gate::allows('manageDocuments', $product),
                'viewCosts' => $canViewCosts,
                'viewFormulas' => $canViewFormulas,
            ],
            'documentTypes' => [
                [
                    'value' => QrDocumentType::TechnicalDataSheet->value,
                    'label' => QrDocumentType::TechnicalDataSheet->label(),
                ],
                [
                    'value' => QrDocumentType::SafetyDataSheet->value,
                    'label' => QrDocumentType::SafetyDataSheet->label(),
                ],
            ],
            'units' => UnitOfMeasure::query()
                ->select('id', 'name', 'symbol')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'rawMaterials' => RawMaterial::query()
                ->with('category:id,name')
                ->where(fn ($q) => $q
                    ->whereHas('category', fn ($cq) => $cq->whereRaw('LOWER(name) LIKE ?', ['%envase%']))
                    ->orWhere('code', 'like', '%bidón%')
                    ->orWhere('code', 'like', '%galón%')
                    ->orWhere('code', 'like', '%tambor%')
                )
                ->select('id', 'code', 'category_id')
                ->get(),
        ]);
    }

    public function edit(Request $request, Product $product): Response
    {
        $this->authorize('update', $product);

        $canViewCosts = $request->user()?->can(Permission::CostsView->value) ?? false;

        return Inertia::render('Products/Edit', [
            // Array explícito: solo viaja lo que el formulario usa; los costos, CIF y umbral incluidos, solo con costs.view.
            'product' => [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'brand' => $product->brand,
                'description' => $product->description,
                'category_id' => $product->category_id,
                'unit_of_measure_id' => $product->unit_of_measure_id,
                ...($canViewCosts ? [
                    'current_cost' => $product->current_cost,
                    'current_price' => $product->current_price,
                    'cif_percentage' => $product->cif_percentage,
                    'price_threshold' => $product->price_threshold,
                ] : []),
                'quality_viscosity_lower' => $product->quality_viscosity_lower,
                'quality_viscosity_upper' => $product->quality_viscosity_upper,
                'quality_fineness_lower' => $product->quality_fineness_lower,
                'quality_fineness_upper' => $product->quality_fineness_upper,
                'quality_solids_lower' => $product->quality_solids_lower,
                'quality_solids_upper' => $product->quality_solids_upper,
                'is_active' => $product->is_active,
            ],
            'hasActiveFormula' => $product->activeFormula()->exists(),
            'categories' => ProductCategory::query()->select('id', 'name')->orderBy('name')->get(),
            'units' => UnitOfMeasure::query()->select('id', 'name', 'symbol')->orderBy('name')->get(),
            'can' => [
                'managePrices' => Gate::allows(Permission::CostsUpdate->value),
                'viewCosts' => $canViewCosts,
            ],
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $validated = $request->validated();

        $cifChanged = array_key_exists('cif_percentage', $validated)
            && $this->hasDecimalChanged($product->cif_percentage, $validated['cif_percentage'] ?? null);
        $thresholdChanged = array_key_exists('price_threshold', $validated)
            && $this->hasDecimalChanged($product->price_threshold, $validated['price_threshold'] ?? null);

        if (($cifChanged || $thresholdChanged) && ! Gate::allows(Permission::CostsUpdate->value)) {
            abort(403, __('No tienes autorización para modificar los márgenes CIF o umbrales de precio.'));
        }

        $activeChanged = array_key_exists('is_active', $validated)
            && (bool) $validated['is_active'] !== (bool) $product->is_active;

        if ($activeChanged && ! Gate::allows(Permission::ProductsDeactivate->value)) {
            abort(403, __('No tienes autorización para activar o desactivar productos.'));
        }

        DB::transaction(function () use ($product, $validated): void {
            $product->update($validated);

            if ($product->wasChanged('cif_percentage') || $product->wasChanged('price_threshold')) {
                $costRecord = $this->productionCostRecalculationService->recalculateForProduct(
                    (int) $product->id,
                    forcePriceRefresh: true
                );

                if ($costRecord === null) {
                    $costStr = (string) ($product->current_cost ?? '0');
                    $cifPercentageStr = (string) ($product->cif_percentage ?? '0');
                    $cifRatio = $this->calculator->div($cifPercentageStr, '100', 4);
                    $cifFactor = $this->calculator->add('1', $cifRatio, 4);
                    $newPrice = $this->calculator->mul($costStr, $cifFactor, 4);

                    $product->updateQuietly(['current_price' => $newPrice]);

                    foreach ($product->variants()->with('packageRawMaterial')->get() as $variant) {
                        $packageCostStr = (string) ($variant->packageRawMaterial?->current_price ?? '0');
                        $presentationStr = (string) ($variant->presentation_value ?? '1');

                        $costTimesPresentation = $this->calculator->mul($costStr, $presentationStr, 4);
                        $newVariantCost = $this->calculator->add($costTimesPresentation, $packageCostStr, 4);
                        $newVariantPrice = $this->calculator->mul($newVariantCost, $cifFactor, 4);

                        $variant->updateQuietly([
                            'current_cost' => $newVariantCost,
                            'current_price' => $newVariantPrice,
                        ]);
                    }
                }
            }
        });

        return redirect()->route('products.index')->with('success', __('Producto actualizado exitosamente.'));
    }

    /**
     * Carga el producto para su ficha, sin costos ni fórmulas si el usuario no tiene el permiso.
     */
    private function productForShow(Product $product, bool $canViewCosts, bool $canViewFormulas): Product
    {
        $product->load([
            'category:id,name',
            'unitOfMeasure:id,name,symbol',
            'variants' => fn ($query) => $query
                ->with(['unitOfMeasure:id,name,symbol', 'packageRawMaterial:id,code,category_id'])
                ->orderBy('code'),
            'productDocuments' => fn ($query) => $query->current()->latest('id'),
        ]);

        if ($canViewFormulas) {
            $product->load(['formulas' => fn ($query) => $query->with('createdBy:id,name')->orderBy('version', 'desc')]);
        }

        if (! $canViewCosts) {
            $product->makeHidden(self::PRODUCT_COST_ATTRIBUTES);
            $product->variants->each(fn (ProductVariant $variant) => $variant->makeHidden(self::VARIANT_COST_ATTRIBUTES));
        }

        return $product;
    }

    private function hasDecimalChanged(string|int|float|null $current, mixed $new): bool
    {
        if ($current === null && $new === null) {
            return false;
        }

        if ($current === null || $new === null) {
            return true;
        }

        return $this->calculator->cmp((string) $current, (string) $new) !== 0;
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $product->delete();

        return redirect()->route('products.index')->with('success', __('Producto eliminado exitosamente.'));
    }
}
