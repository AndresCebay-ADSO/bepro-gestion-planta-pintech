<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ProductionOrderStatus;
use App\Enums\WarehouseType;
use App\Http\Requests\Production\CompleteProductionOrderRequest;
use App\Http\Requests\Production\StoreProductionOrderRequest;
use App\Models\Formula;
use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\ProductionOrderPackagingPlan;
use App\Models\Warehouse;
use App\Services\ProductionOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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
    public function show(ProductionOrder $productionOrder): Response
    {
        $productionOrder->load([
            'product',
            'formula.details.rawMaterial',
            'details.rawMaterial',
            'packagingPlans.productVariant',
            'warehouse',
        ]);

        $orderData = [
            'id' => $productionOrder->id,
            'order_number' => $productionOrder->order_number,
            'status' => $productionOrder->status->value,
            'quantity' => (float) $productionOrder->quantity,
            'actual_quantity' => $productionOrder->actual_quantity !== null ? (float) $productionOrder->actual_quantity : null,
            'yield_percentage' => $productionOrder->yield_percentage !== null ? (float) $productionOrder->yield_percentage : null,
            'planned_date' => optional($productionOrder->planned_date)->toDateString(),
            'completion_date' => optional($productionOrder->completion_date)->toISOString(),
            'viscosity_ku' => $productionOrder->viscosity_ku !== null ? (float) $productionOrder->viscosity_ku : null,
            'grinding_hg' => $productionOrder->grinding_hg !== null ? (float) $productionOrder->grinding_hg : null,
            'agitation_start_time' => optional($productionOrder->agitation_start_time)->format('Y-m-d\TH:i'),
            'agitation_end_time' => optional($productionOrder->agitation_end_time)->format('Y-m-d\TH:i'),
            'packaging_start_time' => optional($productionOrder->packaging_start_time)->format('Y-m-d\TH:i'),
            'packaging_end_time' => optional($productionOrder->packaging_end_time)->format('Y-m-d\TH:i'),
            'responsible_name' => $productionOrder->responsible_name,
            'spillage_quantity' => (float) $productionOrder->spillage_quantity,
            'notes' => $productionOrder->notes,
            'product' => $productionOrder->product ? [
                'id' => $productionOrder->product->id,
                'name' => $productionOrder->product->name,
                'code' => $productionOrder->product->code,
            ] : null,
            'formula' => $productionOrder->formula ? [
                'id' => $productionOrder->formula->id,
                'version' => $productionOrder->formula->version,
            ] : null,
            'warehouse' => $productionOrder->warehouse ? [
                'id' => $productionOrder->warehouse->id,
                'name' => $productionOrder->warehouse->name,
            ] : null,
            'details' => $productionOrder->details->map(fn (ProductionOrderDetail $detail) => [
                'id' => $detail->id,
                'planned_quantity' => (float) $detail->planned_quantity,
                'actual_quantity' => $detail->actual_quantity !== null ? (float) $detail->actual_quantity : null,
                'raw_material' => $detail->rawMaterial ? [
                    'id' => $detail->rawMaterial->id,
                    'code' => $detail->rawMaterial->code,
                ] : null,
            ])->values(),
            'packaging_plans' => $productionOrder->packagingPlans->map(fn (ProductionOrderPackagingPlan $plan) => [
                'id' => $plan->id,
                'planned_units' => (float) $plan->planned_units,
                'actual_units' => $plan->actual_units !== null ? (float) $plan->actual_units : null,
                'product_variant' => $plan->productVariant ? [
                    'id' => $plan->productVariant->id,
                    'presentation_label' => $plan->productVariant->presentation_label,
                ] : null,
            ])->values(),
        ];

        return Inertia::render('Production/Orders/Show', [
            'order' => $orderData,
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
            ->where('type', WarehouseType::Factory->value)
            ->get(['id', 'name']);

        return Inertia::render('Production/Orders/Create', [
            'products' => $products,
            'warehouses' => $warehouses,
        ]);
    }

    /**
     * Crear una nueva orden (Planificación).
     */
    public function store(StoreProductionOrderRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $formula = Formula::findOrFail($validated['formula_id']);
        $formula->load('details');

        // Validar stock antes de crear
        $this->productionOrderService->validateStockForOrder($formula, (float) $validated['quantity'], (int) $validated['warehouse_id']);

        $order = DB::transaction(function () use ($validated, $formula) {
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
                $batch = InventoryBatch::where('raw_material_id', $detail->raw_material_id)
                    ->where('warehouse_id', $validated['warehouse_id'])
                    ->where('remaining_quantity', '>', 0)
                    ->orderBy('entry_date')
                    ->orderBy('id')
                    ->first();

                ProductionOrderDetail::create([
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
                    ProductionOrderPackagingPlan::create([
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
    public function complete(CompleteProductionOrderRequest $request, ProductionOrder $order): RedirectResponse
    {
        $validated = $request->validated();

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
            ->lockForUpdate()
            ->value('order_number');

        $nextSequence = 1;
        if ($lastOrder !== null) {
            $lastSequence = (int) substr($lastOrder, strlen($prefix));
            $nextSequence = $lastSequence + 1;
        }

        return $prefix.str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
    }
}
