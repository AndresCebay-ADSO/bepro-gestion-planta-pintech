<?php

namespace App\Http\Controllers;

use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
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
            ->withQueryString();

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

        if (array_key_exists('current_price', $validated) || array_key_exists('profit_margin', $validated)) {
            $this->authorize('create', PriceList::class);
        }

        Product::create($validated);

        return redirect()->route('products.index')->with('success', __('Producto creado exitosamente.'));
    }

    public function show(Product $product): Response
    {
        $this->authorize('view', $product);

        return Inertia::render('Products/Show', [
            'product' => $product->load([
                'category:id,name',
                'unitOfMeasure:id,name,symbol',
                'formulas' => fn ($q) => $q->with('createdBy:id,name')->orderBy('version', 'desc'),
            ]),
            'can' => [
                'update' => Gate::allows('update', $product),
                'delete' => Gate::allows('delete', $product),
            ],
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

        $priceFields = ['current_cost', 'current_price', 'profit_margin', 'price_threshold'];
        foreach ($priceFields as $field) {
            if (array_key_exists($field, $validated) && (string) ($product->{$field} ?? '') !== (string) $validated[$field]) {
                $this->authorize('create', PriceList::class);
                break;
            }
        }

        $product->update($validated);

        return redirect()->route('products.index')->with('success', __('Producto actualizado exitosamente.'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $product->delete();

        return redirect()->route('products.index')->with('success', __('Producto eliminado exitosamente.'));
    }
}
