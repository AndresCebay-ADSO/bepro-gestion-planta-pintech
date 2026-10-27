<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Formulas\ChangeFormulaActiveAction;
use App\Actions\Formulas\CreateFormulaAction;
use App\Actions\Formulas\DeleteFormulaAction;
use App\Actions\Formulas\SetPriceReferenceFormulaAction;
use App\Actions\Formulas\UpdateFormulaAction;
use App\Enums\Permission;
use App\Filters\FormulaFilter;
use App\Http\Requests\Formulas\IndexFormulaRequest;
use App\Http\Requests\Formulas\StoreFormulaRequest;
use App\Http\Requests\Formulas\UpdateFormulaRequest;
use App\Models\Formula;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FormulaController extends Controller
{
    public function __construct(
        private readonly DeleteFormulaAction $deleteFormula,
        private readonly CreateFormulaAction $createFormula,
        private readonly SetPriceReferenceFormulaAction $setPriceReference,
        private readonly ChangeFormulaActiveAction $changeActive,
        private readonly UpdateFormulaAction $updateFormula,
    ) {}

    public function index(IndexFormulaRequest $request): Response
    {
        $formulas = (new FormulaFilter($request))
            ->apply(Formula::query())
            ->with(['product:id,name', 'createdBy:id,name'])
            ->latest('id')
            ->paginate(15)
            ->onEachSide(1)
            ->withQueryString();

        return Inertia::render('Formulas/Index', [
            'formulas' => $formulas,
            'filters' => $request->validated(),
            'can' => [
                'create' => Gate::allows('create', Formula::class),
                'delete' => $request->user()?->can(Permission::FormulasDelete->value) ?? false,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Formula::class);

        return Inertia::render('Formulas/Create', [
            ...$this->formulaFormOptions(),
            'selectedProductId' => $request->input('product_id'),
            'returnTo' => $this->resolveReturnTo($request),
        ]);
    }

    public function store(StoreFormulaRequest $request): RedirectResponse
    {
        $this->authorize('create', Formula::class);

        $formula = $this->createFormula->execute($request->validated(), (int) $request->user()->id);

        $message = $formula->is_price_reference
            ? __('Fórmula v:version creada. Es la referencia de precio del producto.', ['version' => $formula->version])
            : __('Fórmula v:version creada y activa. El precio del producto sigue saliendo de la v:reference.', [
                'version' => $formula->version,
                'reference' => Formula::query()->where('product_id', $formula->product_id)->priceReference()->value('version'),
            ]);

        return $this->redirectWithContext($request, 'formulas.index', [], 'success', $message);
    }

    public function show(Request $request, Formula $formula): Response
    {
        $this->authorize('view', $formula);

        $formula->load([
            'product:id,name,unit_of_measure_id',
            'product.unitOfMeasure:id,name,symbol',
            'details.rawMaterial:id,code',
            'details.unitOfMeasure:id,name,symbol',
            'createdBy:id,name',
        ]);

        return Inertia::render('Formulas/Show', [
            'formula' => [
                ...$formula->toArray(),
                'has_production_orders' => $formula->productionOrders()->exists(),
            ],
            'returnTo' => $this->resolveReturnTo($request),
            'can' => [
                'update' => Gate::allows('update', $formula),
                'activate' => Gate::allows('activate', $formula),
                'delete' => Gate::allows('delete', $formula),
            ],
        ]);
    }

    public function edit(Formula $formula): Response|RedirectResponse
    {
        $this->authorize('update', $formula);

        // Mismas reglas y mensajes que al guardar (UpdateFormulaAction): usada en una OP, o producto inactivo (P9-13).
        if (($blocker = $this->updateFormula->blocker($formula)) !== null) {
            return redirect()->route('formulas.show', $formula)->with('error', $blocker);
        }

        $formula->load([
            'product:id,name',
            'details:id,formula_id,raw_material_id,quantity,unit_of_measure_id,step_order',
        ]);

        return Inertia::render('Formulas/Edit', [
            'formula' => $formula,
            ...$this->formulaFormOptions(),
        ]);
    }

    public function update(UpdateFormulaRequest $request, Formula $formula): RedirectResponse
    {
        $this->authorize('update', $formula);

        try {
            $this->updateFormula->execute($formula, $request->validated());
        } catch (DomainException $exception) {
            return redirect()->route('formulas.show', $formula)->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('formulas.show', $formula)
            ->with('success', 'Fórmula actualizada exitosamente.');
    }

    public function destroy(Formula $formula): RedirectResponse
    {
        $this->authorize('delete', $formula);

        try {
            $deleted = $this->deleteFormula->execute($formula);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        if (! $deleted) {
            return back()->with('error', __('La fórmula tiene historial (órdenes de producción o costos calculados). Desactívala en su lugar.'));
        }

        return redirect()->route('formulas.index')
            ->with('success', 'Fórmula eliminada exitosamente.');
    }

    /**
     * Activa esta fórmula sin tocar las demás: un producto puede tener varias activas (D-FOR1).
     */
    public function activate(Formula $formula): RedirectResponse
    {
        $this->authorize('activate', $formula);

        try {
            $formula = $this->changeActive->execute($formula, true);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('formulas.show', $formula)
            ->with('success', $formula->is_price_reference
                ? __('Fórmula v:version activada. Es la referencia de precio del producto.', ['version' => $formula->version])
                : __('Fórmula v:version activada.', ['version' => $formula->version]));
    }

    public function deactivate(Formula $formula): RedirectResponse
    {
        $this->authorize('activate', $formula);

        try {
            $this->changeActive->execute($formula, false);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('formulas.show', $formula)
            ->with('success', __('Fórmula v:version desactivada.', ['version' => $formula->version]));
    }

    /**
     * «Usar para el precio»: el producto pasa a costearse con esta fórmula.
     */
    public function priceReference(Formula $formula): RedirectResponse
    {
        $this->authorize('activate', $formula);

        try {
            $changed = $this->setPriceReference->execute($formula);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('formulas.show', $formula)
            ->with('success', $changed
                ? __('El precio del producto ahora sale de la fórmula v:version.', ['version' => $formula->version])
                : __('La fórmula v:version ya era la referencia de precio.', ['version' => $formula->version]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formulaFormOptions(): array
    {
        return [
            'products' => Product::query()
                ->where('is_active', true)
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            // Solo tipo Químico, como exige StoreFormulaRequest.
            'rawMaterials' => RawMaterial::query()
                ->where('is_active', true)
                ->usableInFormulas()
                ->select('id', 'code')
                ->orderBy('code')
                ->get(),
            // StoreFormulaRequest solo acepta unidades activas: el desplegable ofrece las mismas.
            'units' => UnitOfMeasure::query()
                ->selectable()
                ->select('id', 'name', 'symbol')
                ->orderBy('name')
                ->get(),
        ];
    }
}
