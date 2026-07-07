<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Production\BuildProductionOrderExportDataAction;
use App\Actions\Production\BuildProductionOrderShowDataAction;
use App\Actions\Production\CancelProductionOrderAction;
use App\Actions\Production\CompleteProductionOrderAction;
use App\Actions\Production\CreateProductionOrderAction;
use App\Actions\Production\PreviewProductionOrderCostsAction;
use App\Actions\Production\RejectProductionOrderReviewAction;
use App\Actions\Production\StartProductionOrderAction;
use App\Actions\Production\SubmitProductionOrderForReviewAction;
use App\Enums\WarehouseType;
use App\Exports\ProductionOrderExport;
use App\Http\Requests\Production\CancelProductionOrderRequest;
use App\Http\Requests\Production\CompleteProductionOrderRequest;
use App\Http\Requests\Production\PreviewProductionOrderCostsRequest;
use App\Http\Requests\Production\RejectProductionOrderReviewRequest;
use App\Http\Requests\Production\StartProductionOrderRequest;
use App\Http\Requests\Production\StoreProductionOrderRequest;
use App\Http\Requests\Production\SubmitProductionOrderForReviewRequest;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\User;
use App\Models\Warehouse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
        private readonly BuildProductionOrderExportDataAction $buildProductionOrderExportData,
        private readonly SubmitProductionOrderForReviewAction $submitProductionOrderForReview,
        private readonly StartProductionOrderAction $startProductionOrder,
        private readonly RejectProductionOrderReviewAction $rejectProductionOrderReview,
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
            'can' => [
                'create' => auth()->user()?->can('create', ProductionOrder::class) ?? false,
            ],
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
            ->get(['id', 'name', 'presentation_label', 'presentation_value'])
            ->map(fn (ProductVariant $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'presentation_label' => $v->presentation_label,
                'presentation_value' => (float) $v->presentation_value,
            ]);

        $user = auth()->user();
        $includeCosts = $user?->can('previewCosts', $productionOrder) ?? false;

        $qualitySigners = User::role(['admin', 'produccion'])
            ->active()
            ->whereNotNull('job_title')
            ->whereNotNull('signature_path')
            ->get(['id', 'name', 'job_title', 'signature_path'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'job_title' => $u->job_title,
                'signature_url' => $u->signature_url,
            ]);

        return Inertia::render('Production/Orders/Show', [
            'order' => $this->buildProductionOrderShowData->execute($productionOrder, $includeCosts),
            'rawMaterials' => $rawMaterials,
            'availableVariants' => $availableVariants,
            'qualitySigners' => $qualitySigners,
            'returnTo' => $this->resolveReturnTo($request),
            'can' => [
                'startProduction' => $user?->can('startProduction', $productionOrder) ?? false,
                'submitForReview' => $user?->can('submitForReview', $productionOrder) ?? false,
                'complete' => $user?->can('complete', $productionOrder) ?? false,
                'rejectReview' => $user?->can('rejectReview', $productionOrder) ?? false,
                'previewCosts' => $user?->can('previewCosts', $productionOrder) ?? false,
                'updateOperationalData' => $user?->can('updateOperationalData', $productionOrder) ?? false,
            ],
        ]);
    }

    /**
     * Exportar orden de producción como PDF (ficha industrial FPR-01).
     */
    public function exportPdf(ProductionOrder $productionOrder): \Illuminate\Http\Response
    {
        $this->authorize('view', $productionOrder);

        $includeCosts = auth()->user()?->can('previewCosts', $productionOrder) ?? false;
        $orderData = $this->buildProductionOrderExportData->execute($productionOrder, $includeCosts);
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

        $includeCosts = auth()->user()?->can('previewCosts', $productionOrder) ?? false;
        $orderData = $this->buildProductionOrderExportData->execute($productionOrder, $includeCosts);
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
                    'name' => $v->name,
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
        try {
            $order = $this->createProductionOrder->execute(
                data: $request->validated(),
                userId: $this->authenticatedUserId()
            );
        } catch (\DomainException $exception) {
            throw ValidationException::withMessages([
                'formula_id' => $exception->getMessage(),
            ]);
        }

        return redirect()->route('production-orders.show', $order)
            ->with('success', 'Orden de producción creada con éxito.');
    }

    /**
     * Finalizar orden con datos reales de planta.
     */
    public function complete(CompleteProductionOrderRequest $request, ProductionOrder $productionOrder): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $this->completeProductionOrder->execute(
                order: $productionOrder,
                data: $validated,
                userId: $this->authenticatedUserId()
            );
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('production-orders.show', $productionOrder)
            ->with('success', 'Producción finalizada e inventario actualizado.');
    }

    /**
     * Iniciar producción en planta (pending → in_progress).
     */
    public function startProduction(StartProductionOrderRequest $request, ProductionOrder $productionOrder): RedirectResponse
    {
        try {
            $this->startProductionOrder->execute(
                order: $productionOrder,
            );
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('production-orders.show', $productionOrder)
            ->with('success', 'Producción iniciada. Ya puedes registrar los datos de planta.');
    }

    /**
     * Enviar orden a revisión (precierre por operador de planta).
     */
    public function submitForReview(SubmitProductionOrderForReviewRequest $request, ProductionOrder $productionOrder): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $this->submitProductionOrderForReview->execute(
                order: $productionOrder,
                data: $validated,
                userId: $this->authenticatedUserId()
            );
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('production-orders.show', $productionOrder)
            ->with('success', 'Orden enviada a revisión. Producción validará el cierre definitivo.');
    }

    /**
     * Devolver una orden de producción a planta (rechazo de revisión).
     */
    public function rejectReview(RejectProductionOrderReviewRequest $request, ProductionOrder $productionOrder): RedirectResponse
    {
        try {
            $this->rejectProductionOrderReview->execute(
                order: $productionOrder,
                reason: $request->validated('reason'),
            );
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('production-orders.show', $productionOrder)
            ->with('success', 'Orden devuelta a planta para correcciones.');
    }

    /**
     * Cancelar una orden de producción.
     */
    public function cancel(CancelProductionOrderRequest $request, ProductionOrder $productionOrder): RedirectResponse
    {
        try {
            $this->cancelProductionOrder->execute($productionOrder, $request->validated('reason'));

            return redirect()->route('production-orders.show', $productionOrder)
                ->with('success', 'Orden de producción cancelada con éxito.');
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    /**
     * Vista previa de costos estimados durante el cierre de la orden.
     */
    public function previewCosts(PreviewProductionOrderCostsRequest $request, ProductionOrder $productionOrder): JsonResponse
    {
        $validated = $request->validated();

        $remnantGallons = isset($validated['remnant_quantity_gallons'])
            ? (string) $validated['remnant_quantity_gallons']
            : null;

        return response()->json(
            $this->previewProductionOrderCosts->execute(
                order: $productionOrder,
                ingredients: $validated['ingredients'],
                packaging: $validated['packaging'] ?? [],
                remnantGallons: $remnantGallons
            )
        );
    }

    private function authenticatedUserId(): int
    {
        return (int) (auth()->id() ?? throw new \RuntimeException('No authenticated user'));
    }
}
