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
            'packaging' => 'nullable|array',
            'packaging.*.product_variant_id' => 'required_with:packaging|exists:product_variants,id',
            'packaging.*.planned_units' => 'required_with:packaging|numeric|min:0.01',
        ]);

        $formula = Formula::findOrFail($validated['formula_id']);
        $formula->load('details');

        // Validar stock antes de crear
        $this->productionOrderService->validateStockForOrder($formula, (float) $validated['quantity'], (int) $validated['warehouse_id']);

        $order = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $formula) {
            $order = ProductionOrder::create([
                'product_id' => $validated['product_id'],
                'formula_id' => $validated['formula_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'quantity' => $validated['quantity'],
                'planned_date' => $validated['planned_date'],
                'notes' => $validated['notes'] ?? null,
                'order_number' => $this->generateOrderNumber(),
                'status' => ProductionOrderStatus::Pending,
                'created_by' => auth()->id(),
            ]);

            // Crear detalles de ingredientes basados en la fórmula con asignación FIFO de lotes
            foreach ($formula->details as $detail) {
                $plannedQuantity = $detail->quantity * (float) $validated['quantity'];

                // Buscar lote FIFO con stock disponible en la bodega seleccionada
                $batch = \App\Models\InventoryBatch::where('raw_material_id', $detail->raw_material_id)
                    ->where('warehouse_id', $validated['warehouse_id'])
                    ->where('remaining_quantity', '>', 0)
                    ->orderBy('entry_date')
                    ->orderBy('id')
                    ->first();

                \App\Models\ProductionOrderDetail::create([
                    'production_order_id' => $order->id,
                    'raw_material_id' => $detail->raw_material_id,
                    'batch_id' => $batch->id,
                    'planned_quantity' => $plannedQuantity,
                    'unit_cost' => $batch->unit_price,
                    'total_cost' => $plannedQuantity * $batch->unit_price,
                ]);
            }

            // Crear plan de envasado si se proporcionó
            if (! empty($validated['packaging'])) {
                foreach ($validated['packaging'] as $packData) {
                    \App\Models\ProductionOrderPackagingPlan::create([
                        'production_order_id' => $order->id,
                        'product_variant_id' => $packData['product_variant_id'],
                        'planned_units' => $packData['planned_units'],
                    ]);
                }
            }

            return $order;
        });

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

    /**
     * Genera un número de orden secuencial: OP-YYMMDD-XXXX (max 16 chars).
     */
    private function generateOrderNumber(): string
    {
        $prefix = 'OP-'.now()->format('ymd').'-';

        $lastOrder = ProductionOrder::where('order_number', 'like', $prefix.'%')
            ->orderByDesc('order_number')
            ->value('order_number');

        $nextSequence = 1;
        if ($lastOrder !== null) {
            $lastSequence = (int) substr($lastOrder, strlen($prefix));
            $nextSequence = $lastSequence + 1;
        }

        return $prefix.str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
    }
}
