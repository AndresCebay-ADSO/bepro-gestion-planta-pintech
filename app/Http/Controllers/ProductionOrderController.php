<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ProductionOrderStatus;
use App\Enums\WarehouseType;
use App\Exports\ProductionOrderExport;
use App\Http\Requests\Production\CompleteProductionOrderRequest;
use App\Http\Requests\Production\PreviewProductionOrderCostsRequest;
use App\Http\Requests\Production\StoreProductionOrderRequest;
use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\ProductionOrderPackagingPlan;
use App\Models\Warehouse;
use App\Services\ProductionOrderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
        $this->authorize('viewAny', ProductionOrder::class);

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
        $this->authorize('view', $productionOrder);

        return Inertia::render('Production/Orders/Show', [
            'order' => $this->buildOrderData($productionOrder),
        ]);
    }

    /**
     * Exportar orden de producción como PDF (ficha industrial FPR-01).
     */
    public function exportPdf(ProductionOrder $productionOrder): \Illuminate\Http\Response
    {
        $this->authorize('view', $productionOrder);

        $orderData = $this->buildOrderData($productionOrder);
        $filename = "orden-produccion-{$orderData['order_number']}.pdf";

        $logoPath = public_path('images/logo-pintech.png');
        $logoBase64 = file_exists($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView('pdf.production-order', [
            'order' => $orderData,
            'logoBase64' => $logoBase64,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);

        $pdf->setPaper('letter');

        return $pdf->download($filename);
    }

    /**
     * Exportar orden de producción como Excel.
     *
     * NOTE: Diseñado para una orden individual. Si se requiere exportación
     * masiva desde el índice en el futuro, se deberá refactorizar esta clase.
     */
    public function exportExcel(ProductionOrder $productionOrder): BinaryFileResponse
    {
        $this->authorize('view', $productionOrder);

        $orderData = $this->buildOrderData($productionOrder);
        $filename = "orden-produccion-{$orderData['order_number']}.xlsx";

        return Excel::download(new ProductionOrderExport($orderData), $filename);
    }

    /**
     * Mostrar formulario para crear nueva orden.
     */
    public function create(): Response
    {
        $this->authorize('create', ProductionOrder::class);

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
        $this->authorize('create', ProductionOrder::class);

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

            // Crear detalles de ingredientes basados en la fórmula (sin reservar lote en planificación)
            foreach ($formula->details as $detail) {
                $plannedQuantity = $detail->quantity * (float) $validated['quantity'];
                $estimatedUnitCost = $this->productionOrderService->estimateMaterialUnitCostForPlanning(
                    rawMaterialId: (int) $detail->raw_material_id,
                    warehouseId: (int) $validated['warehouse_id'],
                    requiredQuantity: (float) $plannedQuantity
                );

                ProductionOrderDetail::create([
                    'production_order_id' => $order->id,
                    'raw_material_id' => $detail->raw_material_id,
                    'batch_id' => null,
                    'planned_quantity' => $plannedQuantity,
                    'unit_cost' => $estimatedUnitCost,
                    'total_cost' => $plannedQuantity * $estimatedUnitCost,
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
        $this->authorize('update', $order);

        $validated = $request->validated();

        $this->productionOrderService->completeOrder($order, $validated);

        return redirect()->route('production-orders.show', $order)
            ->with('success', 'Producción finalizada e inventario actualizado.');
    }

    /**
     * Vista previa de costos estimados durante el cierre de la orden.
     */
    public function previewCosts(PreviewProductionOrderCostsRequest $request, ProductionOrder $order): JsonResponse
    {
        $validated = $request->validated();

        return response()->json(
            $this->productionOrderService->previewOrderCosts(
                order: $order,
                ingredients: $validated['ingredients'],
                packaging: $validated['packaging'] ?? []
            )
        );
    }

    /**
     * Carga relaciones y transforma la orden en un array estructurado.
     *
     * @return array<string, mixed>
     */
    private function buildOrderData(ProductionOrder $productionOrder): array
    {
        $productionOrder->load([
            'product',
            'formula.details.rawMaterial',
            'details.rawMaterial',
            'packagingPlans.productVariant.packageRawMaterial',
            'finishedInventoryMovements',
            'warehouse',
        ]);

        $finishedCostByVariant = $productionOrder->finishedInventoryMovements
            ->keyBy('product_variant_id');

        $packageRawMaterialRequirements = $productionOrder->packagingPlans
            ->map(fn (ProductionOrderPackagingPlan $plan) => $plan->productVariant?->package_raw_material_id)
            ->filter()
            ->unique()
            ->mapWithKeys(fn ($rawMaterialId) => [(int) $rawMaterialId => 1.0])
            ->all();

        $packageUnitCostEstimates = $this->productionOrderService->estimateMaterialUnitCostsForPlanning(
            warehouseId: (int) $productionOrder->warehouse_id,
            requirementsByMaterialId: $packageRawMaterialRequirements
        );

        $totalFinishedCost = (float) $productionOrder->finishedInventoryMovements
            ->sum(fn ($movement) => (float) $movement->quantity * (float) ($movement->cost_price ?? 0));

        $totalBulkCost = (float) $productionOrder->details
            ->sum(fn (ProductionOrderDetail $detail) => (float) $detail->total_cost);

        return [
            'id' => $productionOrder->id,
            'order_number' => $productionOrder->order_number,
            'status' => $productionOrder->status->value,
            'quantity' => (float) $productionOrder->quantity,
            'actual_quantity' => $productionOrder->actual_quantity !== null ? (float) $productionOrder->actual_quantity : null,
            'yield_real_quantity' => $productionOrder->yield_real_quantity !== null ? (float) $productionOrder->yield_real_quantity : null,
            'yield_theoretical_quantity' => $productionOrder->yield_theoretical_quantity !== null ? (float) $productionOrder->yield_theoretical_quantity : null,
            'yield_variance_quantity' => $productionOrder->yield_variance_quantity !== null ? (float) $productionOrder->yield_variance_quantity : null,
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
                'profit_margin' => $productionOrder->product->profit_margin !== null ? (float) $productionOrder->product->profit_margin : null,
            ] : null,
            'formula' => $productionOrder->formula ? [
                'id' => $productionOrder->formula->id,
                'version' => $productionOrder->formula->version,
            ] : null,
            'warehouse' => $productionOrder->warehouse ? [
                'id' => $productionOrder->warehouse->id,
                'name' => $productionOrder->warehouse->name,
            ] : null,
            'total_bulk_cost' => $totalBulkCost,
            'total_finished_cost' => $totalFinishedCost,
            'details' => $productionOrder->details->map(fn (ProductionOrderDetail $detail) => [
                'id' => $detail->id,
                'raw_material_id' => (int) $detail->raw_material_id,
                'planned_quantity' => (float) $detail->planned_quantity,
                'actual_quantity' => $detail->actual_quantity !== null ? (float) $detail->actual_quantity : null,
                'unit_cost' => (float) $detail->unit_cost,
                'total_cost' => (float) $detail->total_cost,
                'raw_material' => $detail->rawMaterial ? [
                    'id' => $detail->rawMaterial->id,
                    'code' => $detail->rawMaterial->code,
                ] : null,
            ])->values(),
            'packaging_plans' => $productionOrder->packagingPlans->map(function (ProductionOrderPackagingPlan $plan) use ($finishedCostByVariant, $packageUnitCostEstimates) {
                $presentationValue = (float) ($plan->productVariant?->presentation_value ?? 1);
                $costMovement = $finishedCostByVariant->get($plan->product_variant_id);
                $packageRawMaterialId = $plan->productVariant?->package_raw_material_id;
                $packageUnitCostEstimate = $packageRawMaterialId !== null
                    ? ($packageUnitCostEstimates[(int) $packageRawMaterialId] ?? 0.0)
                    : null;

                return [
                    'id' => $plan->id,
                    'planned_units' => (float) $plan->planned_units,
                    'actual_units' => $plan->actual_units !== null ? (float) $plan->actual_units : null,
                    'cost_price' => $costMovement?->cost_price !== null ? (float) $costMovement->cost_price : null,
                    'package_unit_cost_estimate' => $packageUnitCostEstimate,
                    'product_variant' => $plan->productVariant ? [
                        'id' => $plan->productVariant->id,
                        'presentation_label' => $plan->productVariant->presentation_label,
                        'presentation_value' => $presentationValue,
                    ] : null,
                ];
            })->values(),
        ];
    }

    /**
     * Genera un número de orden secuencial: OP-YYMMDD-XXXX (max 16 chars).
     */
    private function generateOrderNumber(): string
    {
        $prefix = 'OP-'.now()->format('ymd').'-';

        if (DB::connection()->getDriverName() === 'pgsql') {
            // PostgreSQL advisory lock to prevent race conditions when 0 rows exist
            $lockKey = crc32($prefix);
            DB::statement('SELECT pg_advisory_xact_lock(?)', [$lockKey]);
        }

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
