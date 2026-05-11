<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ProductionOrderStatus;
use App\Enums\WarehouseType;
use App\Exports\ProductionOrderExport;
use App\Http\Requests\Production\CancelProductionOrderRequest;
use App\Http\Requests\Production\CompleteProductionOrderRequest;
use App\Http\Requests\Production\PreviewProductionOrderCostsRequest;
use App\Http\Requests\Production\StoreProductionOrderRequest;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\ProductionOrderLineAdjustment;
use App\Models\ProductionOrderPackagingPlan;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\Warehouse;
use App\Services\ProductionOrderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

        $rawMaterials = RawMaterial::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code'])
            ->map(fn (RawMaterial $rm) => [
                'id' => $rm->id,
                'label' => $rm->code,
            ]);

        $availableVariants = ProductVariant::query()
            ->where('product_id', $productionOrder->product_id)
            ->where('is_active', true)
            ->get(['id', 'sku', 'presentation_label', 'presentation_value'])
            ->map(fn (ProductVariant $v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'presentation_label' => $v->presentation_label,
                'presentation_value' => (float) $v->presentation_value,
            ]);

        return Inertia::render('Production/Orders/Show', [
            'order' => $this->buildOrderData($productionOrder),
            'rawMaterials' => $rawMaterials,
            'availableVariants' => $availableVariants,
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

        $order = $this->productionOrderService->createOrder($request->validated());

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

        try {
            $this->productionOrderService->completeOrder($order, $validated);
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('production-orders.show', $order)
            ->with('success', 'Producción finalizada e inventario actualizado.');
    }

    /**
     * Cancelar una orden de producción.
     */
    public function cancel(CancelProductionOrderRequest $request, ProductionOrder $order): RedirectResponse
    {
        $this->authorize('delete', $order);

        try {
            $this->productionOrderService->cancelOrder($order, $request->validated('reason'));

            return redirect()->route('production-orders.show', $order)
                ->with('success', 'Orden de producción cancelada con éxito.');
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    /**
     * Vista previa de costos estimados durante el cierre de la orden.
     */
    public function previewCosts(PreviewProductionOrderCostsRequest $request, ProductionOrder $order): JsonResponse
    {
        $this->authorize('view', $order);

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
            'lineAdjustments.rawMaterial',
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

        $pdfMaterials = $this->buildPdfMaterialsPayload($productionOrder);

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
            'pdf_materials' => $pdfMaterials,
            'details' => $productionOrder->details->map(fn (ProductionOrderDetail $detail) => [
                'id' => $detail->id,
                'raw_material_id' => (int) $detail->raw_material_id,
                'step_order' => (int) $detail->step_order,
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
            'line_adjustments' => $productionOrder->lineAdjustments->map(fn (ProductionOrderLineAdjustment $adj) => [
                'id' => $adj->id,
                'raw_material_id' => (int) $adj->raw_material_id,
                'quantity' => (float) $adj->quantity,
                'reason' => $adj->reason,
                'notes' => $adj->notes,
                'created_at' => $adj->created_at?->toISOString(),
                'raw_material' => $adj->rawMaterial ? [
                    'id' => $adj->rawMaterial->id,
                    'code' => $adj->rawMaterial->code,
                ] : null,
            ])->values(),
        ];
    }

    /**
     * Filas para PDF/Excel: pasos ordenados por step_order (órdenes no completadas)
     * o consolidado por materia prima (orden completada).
     *
     * @return array{mode: string, rows: list<array<string, mixed>>}
     */
    private function buildPdfMaterialsPayload(ProductionOrder $order): array
    {
        $details = $order->details->sortBy('step_order')->values();

        if ($order->status === ProductionOrderStatus::Completed) {
            $rows = $details->groupBy('raw_material_id')
                ->map(function ($group) {
                    /** @var ProductionOrderDetail $first */
                    $first = $group->first();

                    return [
                        'raw_material_code' => $first->rawMaterial->code ?? 'N/A',
                        'raw_material_name' => $first->rawMaterial->code ?? 'N/A',
                        'planned_quantity' => round((float) $group->sum(fn (ProductionOrderDetail $d) => (float) $d->planned_quantity), 4),
                        'actual_quantity' => round((float) $group->sum(fn (ProductionOrderDetail $d) => (float) ($d->actual_quantity ?? 0)), 4),
                    ];
                })
                ->values()
                ->sortBy('raw_material_code')
                ->values()
                ->all();

            return [
                'mode' => 'consolidated',
                'rows' => $rows,
            ];
        }

        $rows = [];

        foreach ($details as $detail) {
            $qty = (float) $detail->planned_quantity;

            $rows[] = [
                'step_order' => (int) $detail->step_order,
                'raw_material_code' => $detail->rawMaterial->code ?? 'N/A',
                'raw_material_name' => $detail->rawMaterial->code ?? 'N/A',
                'planned_quantity' => $qty,
                'actual_quantity' => $detail->actual_quantity !== null ? (float) $detail->actual_quantity : null,
            ];
        }

        return [
            'mode' => 'steps',
            'rows' => $rows,
        ];
    }
}
