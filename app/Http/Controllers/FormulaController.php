<?php

namespace App\Http\Controllers;

use App\Http\Requests\Formulas\StoreFormulaRequest;
use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FormulaController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Formula::class);

        $search = strtolower((string) $request->input('search'));
        $productId = $request->input('product_id');

        $formulas = Formula::query()
            ->with(['product:id,code,name', 'createdBy:id,name'])
            ->when($search, function ($query) use ($search) {
                $query->whereHas('product', function ($q) use ($search) {
                    $q->whereRaw('LOWER(code) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
                });
            })
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Formulas/Index', [
            'formulas' => $formulas,
            'filters' => [
                'search' => $search,
                'product_id' => $productId,
            ],
            'can' => [
                'create' => Gate::allows('create', Formula::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Formula::class);

        return Inertia::render('Formulas/Create', [
            'products' => Product::query()
                ->where('is_active', true)
                ->select('id', 'code', 'name')
                ->orderBy('code')
                ->get(),
            'rawMaterials' => RawMaterial::query()
                ->where('is_active', true)
                ->select('id', 'code')
                ->orderBy('code')
                ->get(),
            'units' => UnitOfMeasure::query()
                ->select('id', 'name', 'symbol')
                ->orderBy('name')
                ->get(),
            'selectedProductId' => $request->input('product_id'),
        ]);
    }

    public function store(StoreFormulaRequest $request): RedirectResponse
    {
        $this->authorize('create', Formula::class);

        $validated = $request->validated();

        DB::transaction(function () use ($validated, $request) {
            // Auto-increment version for this product
            $nextVersion = Formula::where('product_id', $validated['product_id'])
                ->withTrashed()
                ->max('version') + 1;

            // Deactivate all existing formulas for this product
            Formula::where('product_id', $validated['product_id'])
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $formula = Formula::create([
                'product_id' => $validated['product_id'],
                'version' => $nextVersion,
                'is_active' => true,
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($validated['details'] as $detail) {
                FormulaDetail::create([
                    'formula_id' => $formula->id,
                    'raw_material_id' => $detail['raw_material_id'],
                    'quantity' => $detail['quantity'],
                    'unit_of_measure_id' => $detail['unit_of_measure_id'],
                ]);
            }
        });

        return redirect()->route('formulas.index')
            ->with('success', 'Fórmula creada exitosamente y marcada como versión activa.');
    }

    public function show(Formula $formula): Response
    {
        $this->authorize('view', $formula);

        $formula->load([
            'product:id,code,name,unit_of_measure_id',
            'product.unitOfMeasure:id,name,symbol',
            'details.rawMaterial:id,code',
            'details.unitOfMeasure:id,name,symbol',
            'createdBy:id,name',
        ]);

        return Inertia::render('Formulas/Show', [
            'formula' => $formula,
            'can' => [
                'update' => Gate::allows('update', $formula),
                'delete' => Gate::allows('delete', $formula),
            ],
        ]);
    }

    public function destroy(Formula $formula): RedirectResponse
    {
        $this->authorize('delete', $formula);

        $formula->delete();

        return redirect()->route('formulas.index')
            ->with('success', 'Fórmula eliminada exitosamente.');
    }

    /**
     * Activates this formula version and deactivates all others for the same product.
     */
    public function activate(Formula $formula): RedirectResponse
    {
        $this->authorize('update', $formula);

        DB::transaction(function () use ($formula) {
            Formula::where('product_id', $formula->product_id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $formula->update(['is_active' => true]);
        });

        return redirect()->route('formulas.show', $formula)
            ->with('success', 'Fórmula v'.$formula->version.' activada correctamente.');
    }
}
