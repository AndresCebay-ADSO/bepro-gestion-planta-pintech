<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Quotations\BuildQuotationPdfDataAction;
use App\Enums\PaymentMethod;
use App\Enums\QuotationItemType;
use App\Enums\QuotationStatus;
use App\Enums\QuotationValidity;
use App\Http\Requests\Quotations\ConvertQuotationToOrderRequest;
use App\Http\Requests\Quotations\StoreQuotationRequest;
use App\Http\Requests\Quotations\UpdateQuotationRequest;
use App\Http\Requests\Quotations\UpdateQuotationStatusRequest;
use App\Models\Client;
use App\Models\Quotation;
use App\Services\QuotationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class QuotationController extends Controller
{
    public function __construct(
        private readonly QuotationService $quotationService,
        private readonly BuildQuotationPdfDataAction $buildQuotationPdfData
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Quotation::class);

        $user = auth()->user();
        $isAdmin = $user?->hasRole('admin') ?? false;
        $status = $this->resolveStatusFilter(request('status'));
        $search = strtolower((string) request('search', ''));

        $quotations = Quotation::query()
            ->with(['client', 'creator'])
            ->withCount('items')
            ->unless($isAdmin, fn ($q) => $q->where('created_by', $user?->id))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($query) use ($search): void {
                    $driver = $query->getConnection()->getDriverName();
                    $castType = in_array($driver, ['pgsql', 'sqlite'], true) ? 'TEXT' : 'CHAR';

                    $query->whereRaw("CAST(quotation_number AS {$castType}) LIKE ?", ["%{$search}%"])
                        ->orWhereRaw('LOWER(client_business_name) LIKE ?', ["%{$search}%"])
                        ->orWhereHas('client', fn ($cq) => $cq->whereRaw('LOWER(business_name) LIKE ?', ["%{$search}%"]));
                });
            })
            ->latest()
            ->paginate(15)
            ->onEachSide(1)
            ->withQueryString()
            ->through(fn (Quotation $quotation) => [
                'id' => $quotation->id,
                'quotation_number' => $quotation->quotation_number,
                'client' => [
                    'id' => $quotation->client_id,
                    'business_name' => $quotation->client_business_name ?? $quotation->client?->business_name,
                ],
                'creator' => $isAdmin && $quotation->creator ? [
                    'name' => $quotation->creator->name,
                ] : null,
                'status' => $quotation->status->value,
                'status_label' => $quotation->status->label(),
                'quotation_date' => $quotation->quotation_date?->format('d/m/Y'),
                'total' => (float) $quotation->total,
                'items_count' => $quotation->items_count,
                'created_at' => $quotation->created_at?->format('d/m/Y'),
            ]);

        return Inertia::render('Quotations/Index', [
            'quotations' => $quotations,
            'filters' => [
                'search' => request('search'),
                'status' => $status,
            ],
            'can' => [
                'create' => $user?->can('create', Quotation::class) ?? false,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Quotation::class);

        return Inertia::render('Quotations/Create', [
            'clients' => $this->clientOptions(),
            'products' => $this->quotationService->catalogProducts(),
            'validityDaysOptions' => $this->enumOptions(QuotationValidity::cases()),
            'paymentMethodOptions' => $this->enumOptions(PaymentMethod::cases()),
            'itemTypeOptions' => $this->enumOptions(QuotationItemType::cases()),
        ]);
    }

    public function store(StoreQuotationRequest $request): RedirectResponse
    {
        $quotation = $this->quotationService->create(
            $request->validated(),
            $request->user()
        );

        return redirect()->route('quotations.show', $quotation)
            ->with('success', __('Cotización creada con éxito.'));
    }

    public function show(Quotation $quotation): Response
    {
        $this->authorize('view', $quotation);

        $quotation->load(['client', 'creator', 'items.product', 'items.productVariant', 'salesOrder']);

        $salesOrder = $quotation->salesOrder;

        return Inertia::render('Quotations/Show', [
            'quotation' => $this->buildQuotationData($quotation),
            'can' => [
                'update' => auth()->user()?->can('update', $quotation) ?? false,
                'exportPdf' => auth()->user()?->can('exportPdf', $quotation) ?? false,
                'updateStatus' => auth()->user()?->can('updateStatus', $quotation) ?? false,
                'convertToOrder' => auth()->user()?->can('convertToOrder', $quotation) ?? false,
                'viewSalesOrder' => $salesOrder !== null && (auth()->user()?->can('view', $salesOrder) ?? false),
            ],
            'salesOrderId' => $quotation->convert_to_order_id,
            'statusOptions' => array_map(
                fn (QuotationStatus $status) => ['value' => $status->value, 'label' => $status->label()],
                QuotationStatus::cases()
            ),
        ]);
    }

    public function edit(Quotation $quotation): Response
    {
        $this->authorize('update', $quotation);

        $quotation->load(['items.product', 'items.productVariant']);

        return Inertia::render('Quotations/Edit', [
            'quotation' => $this->buildQuotationData($quotation),
            'clients' => $this->clientOptions(),
            'products' => $this->quotationService->catalogProducts(),
            'validityDaysOptions' => $this->enumOptions(QuotationValidity::cases()),
            'paymentMethodOptions' => $this->enumOptions(PaymentMethod::cases()),
            'itemTypeOptions' => $this->enumOptions(QuotationItemType::cases()),
        ]);
    }

    public function update(UpdateQuotationRequest $request, Quotation $quotation): RedirectResponse
    {
        $quotation = $this->quotationService->update($quotation, $request->validated());

        return redirect()->route('quotations.show', $quotation)
            ->with('success', __('Cotización actualizada con éxito.'));
    }

    public function updateStatus(UpdateQuotationStatusRequest $request, Quotation $quotation): RedirectResponse
    {
        $status = QuotationStatus::from($request->validated('status'));
        $this->quotationService->updateStatus($quotation, $status);

        return redirect()->route('quotations.show', $quotation)
            ->with('success', __('Estado de la cotización actualizado.'));
    }

    public function convertToOrder(ConvertQuotationToOrderRequest $request, Quotation $quotation): RedirectResponse
    {
        $order = $this->quotationService->convertToOrder(
            $quotation,
            $request->validated()
        );

        return redirect()->route('sales-orders.show', $order)
            ->with('success', __('Cotización convertida en pedido exitosamente.'));
    }

    public function exportPdf(Quotation $quotation): \Illuminate\Http\Response
    {
        $this->authorize('exportPdf', $quotation);

        $quotationData = $this->buildQuotationPdfData->execute($quotation);
        $filename = 'COT. '.($quotationData['quotation_number'] ?? $quotation->id).'.pdf';

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView('pdf.quotation', [
            'quotation' => $quotationData,
            'beproLogoBase64' => $this->imageToBase64(public_path('images/firma-calidad.jpg')),
            'pintechLogoBase64' => $this->imageToBase64(public_path('images/beprologoqr.png')),
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);

        $pdf->setPaper('letter');

        return $pdf->download($filename);
    }

    private function imageToBase64(string $path): ?string
    {
        if (! file_exists($path)) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
    }

    /**
     * @return array<int, Client>
     */
    private function clientOptions(): array
    {
        return Client::query()
            ->active()
            ->orderBy('business_name')
            ->get(['id', 'business_name', 'nit', 'contact_name', 'phone'])
            ->all();
    }

    /**
     * @param  array<int, \BackedEnum>  $cases
     * @return array<int, array{id: int|string, label: string}>
     */
    private function enumOptions(array $cases): array
    {
        return array_map(
            fn (\BackedEnum $case) => [
                'id' => $case->value,
                'label' => method_exists($case, 'label') ? $case->label() : (string) $case->value,
            ],
            $cases
        );
    }

    private function resolveStatusFilter(mixed $status): ?string
    {
        if (! is_string($status) || $status === '') {
            return null;
        }

        return QuotationStatus::tryFrom($status)?->value;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildQuotationData(Quotation $quotation): array
    {
        return [
            'id' => $quotation->id,
            'quotation_number' => $quotation->quotation_number,
            'client_id' => $quotation->client_id,
            'client' => [
                'id' => $quotation->client_id,
                'business_name' => $quotation->client_business_name,
                'nit' => $quotation->client_nit,
                'contact_name' => $quotation->client_contact_name,
                'phone' => $quotation->client_phone,
                'shipping_address' => $quotation->client?->shipping_address,
            ],
            'technology' => $quotation->technology,
            'line' => $quotation->line,
            'thickness_mils' => $quotation->thickness_mils,
            'application_method' => $quotation->application_method,
            'quotation_date' => $quotation->quotation_date?->format('Y-m-d'),
            'validity_days' => $quotation->validity_days?->value,
            'validity_days_label' => $quotation->validity_days?->label(),
            'payment_method' => $quotation->payment_method?->value,
            'payment_method_label' => $quotation->payment_method?->label(),
            'delivery_time' => $quotation->delivery_time,
            'area' => $quotation->area,
            'notes' => $quotation->notes,
            'subtotal' => (float) $quotation->subtotal,
            'iva_percentage' => (float) $quotation->iva_percentage,
            'iva_amount' => (float) $quotation->iva_amount,
            'total' => (float) $quotation->total,
            'status' => $quotation->status->value,
            'status_label' => $quotation->status->label(),
            'items' => $quotation->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'product' => [
                    'id' => $item->product?->id,
                    'code' => $item->product?->code,
                    'name' => $item->product?->name,
                ],
                'product_variant' => $item->productVariant ? [
                    'id' => $item->productVariant->id,
                    'name' => $item->productVariant->name,
                    'presentation_label' => $item->productVariant->presentation_label,
                ] : null,
                'type' => $item->type?->value,
                'type_label' => $item->type?->label(),
                'description' => $item->description,
                'color' => $item->color,
                'quantity' => (float) $item->quantity,
                'list_unit_price' => (float) $item->list_unit_price,
                'price_adjustment_pct' => (float) $item->price_adjustment_pct,
                'unit_price' => (float) $item->unit_price,
                'subtotal' => (float) $item->subtotal,
                'sort_order' => $item->sort_order,
            ]),
            'created_at' => $quotation->created_at?->format('d/m/Y H:i'),
            'creator' => $quotation->creator ? [
                'name' => $quotation->creator->name,
                'email' => $quotation->creator->email,
            ] : null,
        ];
    }
}
