<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings\Catalogs;

use App\Actions\Shared\DeleteUnusedRecordAction;
use App\Filters\SupplierFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogs\IndexSupplierRequest;
use App\Http\Requests\Catalogs\StoreSupplierRequest;
use App\Http\Requests\Catalogs\UpdateSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catálogo de proveedores, en Configuración → Catálogos.
 */
class SupplierController extends Controller
{
    public function __construct(
        private readonly DeleteUnusedRecordAction $deleteUnused,
    ) {}

    public function index(IndexSupplierRequest $request): Response
    {
        $suppliers = (new SupplierFilter($request))
            ->apply(Supplier::query()->withCount('inventoryBatches'))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->onEachSide(1)
            ->withQueryString()
            ->through(fn (Supplier $supplier): array => [
                ...$this->supplierData($supplier),
                // Con lotes ya no se elimina (la clave foránea RESTRICT lo impide): se desactiva.
                'in_use' => $supplier->inventory_batches_count > 0,
                'can' => [
                    'update' => Gate::allows('update', $supplier),
                    'delete' => Gate::allows('delete', $supplier),
                ],
            ]);

        return Inertia::render('Settings/Catalogs/Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => $request->validated(),
            'can' => [
                'create' => Gate::allows('create', Supplier::class),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Supplier::class);

        return Inertia::render('Settings/Catalogs/Suppliers/Create');
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $this->authorize('create', Supplier::class);

        Supplier::create($request->validated());

        Inertia::flash('success', __('Proveedor registrado exitosamente.'));

        return redirect()
            ->route('catalogs.suppliers.index');
    }

    public function edit(Supplier $supplier): Response
    {
        $this->authorize('update', $supplier);

        return Inertia::render('Settings/Catalogs/Suppliers/Edit', [
            'supplier' => [
                ...$this->supplierData($supplier),
                'email' => $supplier->email,
                'notes' => $supplier->notes,
            ],
        ]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $supplier->update($request->validated());

        Inertia::flash('success', __('Proveedor actualizado exitosamente.'));

        return redirect()
            ->route('catalogs.suppliers.index');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        // Solo si nunca se usó (docs/POLITICA_ELIMINACION.md): la clave foránea RESTRICT de los lotes decide.
        if (! $this->deleteUnused->execute($supplier)) {
            Inertia::flash('error', __('El proveedor tiene lotes registrados. Desactívalo en su lugar.'));

            return back();
        }

        Inertia::flash('success', __('Proveedor eliminado exitosamente.'));

        return redirect()
            ->route('catalogs.suppliers.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function supplierData(Supplier $supplier): array
    {
        return [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'nit' => $supplier->nit,
            'contact_name' => $supplier->contact_name,
            'phone' => $supplier->phone,
            'is_active' => $supplier->is_active,
        ];
    }
}
