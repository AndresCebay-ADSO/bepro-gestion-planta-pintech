<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ProductionOrderStatus;
use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\Warehouse;
use App\Services\ProductionOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductionOrderController extends Controller
{
    public function __construct(
        private readonly ProductionOrderService $productionOrderService
    ) {}

    /**
     * Listado de órdenes de producción.
     */
    public function index(): Response
    {
        $orders = ProductionOrder::query()
            ->with(['product', 'formula', 'warehouse'])
            ->latest()
            ->paginate(15)
            ->onEachSide(1);

        return Inertia::render('Production/Orders/Index', [
            'orders' => $orders,
        ]);
    }

    /**
     * Detalle de una orden para consulta o cierre.
     */
    public function show(ProductionOrder $order): Response
    {
        $order->load([
            'product',
            'formula.details.rawMaterial',
            'details.rawMaterial',
            'packagingPlans.productVariant',
        ]);

        return Inertia::render('Production/Orders/Show', [
            'order' => $order,
        ]);
    }

    /**
     * Mostrar formulario para crear nueva orden.
     */
    public function create(): Response
    {
        $products = Product::query()
            ->with([
                'formulas' => fn ($q) => $q->where('is_active', true),
                'variants' => fn ($q) => $q->where('is_active', true),
            ])
            ->where('is_active', true)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'formulas' => $p->formulas->map(fn ($f) => [
                    'id' => $f->id,
                    'version' => $f->version,
                    'is_active' => $f->is_active,
                ]),
                'variants' => $p->variants->map(fn ($v) => [
                    'id' => $v->id,
                    'sku' => $v->sku,
                    'presentation_label' => $v->presentation_label,
                    'presentation_value' => $v->presentation_value,
                ]),
            ]);

        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->get(['id', 'name']);

        return Inertia::render('Production/Orders/Create', [
            'products' => $products,
            'warehouses' => $warehouses,
        ]);
    }

    /**
     * Crear una nueva orden (Planificación).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'formula_id' => 'required|exists:formulas,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0.01',
            'planned_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $formula = Formula::findOrFail($validated['formula_id']);

        // Validar stock antes de crear (Guardia de Paso C)
        $this->productionOrderService->validateStockForOrder($formula, (float) $validated['quantity']);

        // Lógica de creación (Simplificada para este paso)
        $order = ProductionOrder::create([
            ...$validated,
            'order_number' => 'OP-'.strtoupper(uniqid()),
            'status' => ProductionOrderStatus::Pending,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('production-orders.show', $order)
            ->with('success', 'Orden de producción creada con éxito.');
    }

    /**
     * Finalizar orden con datos reales de planta.
     */
    public function complete(Request $request, ProductionOrder $order): RedirectResponse
    {
        $validated = $request->validate([
            'actual_yield_quantity' => 'nullable|numeric|min:0',
            'viscosity_ku' => 'nullable|numeric|min:0',
            'grinding_hg' => 'nullable|numeric|min:0',
            'agitation_start_time' => 'nullable|date',
            'agitation_end_time' => 'nullable|date',
            'packaging_start_time' => 'nullable|date',
            'packaging_end_time' => 'nullable|date',
            'responsible_name' => 'nullable|string|max:255',
            'spillage_quantity' => 'nullable|numeric|min:0',
            'ingredients' => 'required|array',
            'ingredients.*.id' => 'required|exists:production_order_details,id',
            'ingredients.*.actual_quantity' => 'required|numeric|min:0',
            'packaging' => 'required|array',
            'packaging.*.id' => 'required|exists:production_order_packaging_plan,id',
            'packaging.*.actual_units' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $this->productionOrderService->completeOrder($order, $validated);

        return redirect()->route('production-orders.show', $order)
            ->with('success', 'Producción finalizada e inventario actualizado.');
    }
}
