<?php

namespace App\Http\Controllers;

use App\Enums\QrDocumentType;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Services\DecimalCalculator;
use App\Services\ProductionCostRecalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductionCostRecalculationService $productionCostRecalculationService,
        private readonly DecimalCalculator $calculator
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $search = strtolower((string) $request->input('search'));

        $products = Product::query()
            ->with(['category:id,name', 'unitOfMeasure:id,name,symbol'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(code) LIKE ?', ["%{$search}%"]);
                });
            })
            ->latest('id')
            ->paginate(15)
            ->onEachSide(1)
            ->withQueryString()
            ->through(fn (Product $product) => [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'is_active' => $product->is_active,
                'current_price' => $product->current_price,
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
            'filters' => [
                'search' => $search,
            ],
            'can' => [
                'create' => Gate::allows('create', Product::class),
                'managePrices' => Gate::allows('create', PriceList::class),
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
                'managePrices' => Gate::allows('create', PriceList::class),
            ],
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $validated = $request->validated();

        if (array_key_exists('current_price', $validated)) {
            $this->authorize('create', PriceList::class);
        }

        Product::create($validated);

        return redirect()->route('products.index')->with('success', __('Producto creado exitosamente.'));
    }

    public function show(Request $request, Product $product): Response
    {
        $this->authorize('view', $product);

        return Inertia::render('Products/Show', [
            'returnTo' => $this->resolveReturnTo($request),
            'product' => $product->load([
                'category:id,name',
                'unitOfMeasure:id,name,symbol',
                'variants' => fn ($query) => $query
                    ->with(['unitOfMeasure:id,name,symbol', 'packageRawMaterial:id,code,category_id'])
                    ->orderBy('code'),
                'formulas' => fn ($q) => $q->with('createdBy:id,name')->orderBy('version', 'desc'),
                'productDocuments' => fn ($query) => $query->current()->latest('id'),
            ]),
            'can' => [
                'update' => Gate::allows('update', $product),
                'delete' => Gate::allows('delete', $product),
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

    public function edit(Product $product): Response
    {
        $this->authorize('update', $product);

        return Inertia::render('Products/Edit', [
            'product' => $product,
            'categories' => ProductCategory::query()->select('id', 'name')->orderBy('name')->get(),
            'units' => UnitOfMeasure::query()->select('id', 'name', 'symbol')->orderBy('name')->get(),
            'can' => [
                'managePrices' => Gate::allows('create', PriceList::class),
            ],
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $validated = $request->validated();
        $priceWasManuallyChanged = array_key_exists('current_price', $validated)
            && (string) ($product->current_price ?? '') !== (string) ($validated['current_price'] ?? '');

        if ($priceWasManuallyChanged) {
            $this->authorize('create', PriceList::class);
        }

        $product->update($validated);

        if (
            ($product->wasChanged('profit_margin') || $product->wasChanged('price_threshold') || $product->wasChanged('current_cost'))
            && ! $priceWasManuallyChanged
        ) {
            $costRecord = $this->productionCostRecalculationService->recalculateForProduct(
                (int) $product->id,
                forcePriceRefresh: true
            );

            if ($costRecord === null) {
                $costStr = (string) ($product->current_cost ?? '0');
                $marginStr = (string) ($product->profit_margin ?? '0');
                $marginRatio = $this->calculator->div($marginStr, '100', 4);
                $marginFactor = $this->calculator->add('1', $marginRatio, 4);
                $newPrice = $this->calculator->mul($costStr, $marginFactor, 4);

                $product->updateQuietly(['current_price' => $newPrice]);

                foreach ($product->variants()->with('packageRawMaterial')->get() as $variant) {
                    $packageCostStr = (string) ($variant->packageRawMaterial?->current_price ?? '0');
                    $presentationStr = (string) ($variant->presentation_value ?? '1');

                    $costTimesPresentation = $this->calculator->mul($costStr, $presentationStr, 4);
                    $newVariantCost = $this->calculator->add($costTimesPresentation, $packageCostStr, 4);
                    $newVariantPrice = $this->calculator->mul($newVariantCost, $marginFactor, 4);

                    $variant->updateQuietly([
                        'current_cost' => $newVariantCost,
                        'current_price' => $newVariantPrice,
                    ]);
                }
            }
        }

        return redirect()->route('products.index')->with('success', __('Producto actualizado exitosamente.'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $product->delete();

        return redirect()->route('products.index')->with('success', __('Producto eliminado exitosamente.'));
    }
}
