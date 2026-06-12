<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Production\BuildProductionOrderExportDataAction;
use App\Actions\Production\BuildProductionOrderShowDataAction;
use App\Actions\Production\CancelProductionOrderAction;
use App\Actions\Production\CompleteProductionOrderAction;
use App\Actions\Production\CreateProductionOrderAction;
use App\Actions\Production\PreviewProductionOrderCostsAction;
use App\Enums\WarehouseType;
use App\Exports\ProductionOrderExport;
use App\Http\Requests\Production\CancelProductionOrderRequest;
use App\Http\Requests\Production\CompleteProductionOrderRequest;
use App\Http\Requests\Production\PreviewProductionOrderCostsRequest;
use App\Http\Requests\Production\StoreProductionOrderRequest;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\Warehouse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductionOrderController extends Controller
{
    public function __construct(
        private readonly CreateProductionOrderAction $createProductionOrder,
        private readonly CompleteProductionOrderAction $completeProductionOrder,
        private readonly CancelProductionOrderAction $cancelProductionOrder,
        private readonly PreviewProductionOrderCostsAction $previewProductionOrderCosts,
        private readonly BuildProductionOrderShowDataAction $buildProductionOrderShowData,
        private readonly BuildProductionOrderExportDataAction $buildProductionOrderExportData
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
    public function show(Request $request, ProductionOrder $productionOrder): Response
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
            'order' => $this->buildProductionOrderShowData->execute($productionOrder),
            'rawMaterials' => $rawMaterials,
            'availableVariants' => $availableVariants,
            'returnTo' => $this->resolveReturnTo($request),
        ]);
    }

    /**
     * Exportar orden de producción como PDF (ficha industrial FPR-01).
     */
    public function exportPdf(ProductionOrder $productionOrder): \Illuminate\Http\Response
    {
        $this->authorize('view', $productionOrder);

        $orderData = $this->buildProductionOrderExportData->execute($productionOrder);
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

        $orderData = $this->buildProductionOrderExportData->execute($productionOrder);
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

        $order = $this->createProductionOrder->execute(
            data: $request->validated(),
            userId: $this->authenticatedUserId()
        );

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
            $this->completeProductionOrder->execute(
                order: $order,
                data: $validated,
                userId: $this->authenticatedUserId()
            );
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
            $this->cancelProductionOrder->execute($order, $request->validated('reason'));

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
            $this->previewProductionOrderCosts->execute(
                order: $order,
                ingredients: $validated['ingredients'],
                packaging: $validated['packaging'] ?? []
            )
        );
    }

    private function authenticatedUserId(): int
    {
        return (int) (auth()->id() ?? throw new \RuntimeException('No authenticated user'));
    }
}
