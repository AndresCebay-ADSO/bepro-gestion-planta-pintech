<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaintDevelopmentRequestStatus;
use App\Http\Requests\PaintDevelopmentRequests\StorePaintDevelopmentRequest;
use App\Http\Requests\PaintDevelopmentRequests\UpdatePaintDevelopmentRequest;
use App\Http\Requests\PaintDevelopmentRequests\UpdatePaintDevelopmentRequestStatus;
use App\Models\PaintDevelopmentRequest;
use App\Services\PaintDevelopmentRequestService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PaintDevelopmentRequestController extends Controller
{
    public function __construct(
        private readonly PaintDevelopmentRequestService $service,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', PaintDevelopmentRequest::class);

        $user = auth()->user();
        $isAdminOrProduction = $user?->hasAnyRole(['admin', 'produccion']) ?? false;
        $status = $this->resolveStatusFilter(request('status'));
        $search = strtolower((string) request('search', ''));

        $requests = PaintDevelopmentRequest::query()
            ->with(['creator'])
            ->unless($isAdminOrProduction, fn ($q) => $q->where('created_by', $user?->id))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($query) use ($search): void {
                    $driver = $query->getConnection()->getDriverName();
                    $castType = in_array($driver, ['pgsql', 'sqlite'], true) ? 'TEXT' : 'CHAR';

                    $query->whereRaw("CAST(request_number AS {$castType}) LIKE ?", ["%{$search}%"])
                        ->orWhereRaw('LOWER(project_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(client_name) LIKE ?', ["%{$search}%"]);
                });
            })
            ->latest()
            ->paginate(15)
            ->onEachSide(1)
            ->withQueryString()
            ->through(fn (PaintDevelopmentRequest $request) => [
                'id' => $request->id,
                'request_number' => $request->request_number,
                'client_name' => $request->client_name,
                'creator' => $isAdminOrProduction && $request->creator ? [
                    'name' => $request->creator->name,
                ] : null,
                'status' => $request->status->value,
                'status_label' => $request->status->label(),
                'project_name' => $request->project_name,
                'sample_due_date' => $request->sample_due_date?->format('d/m/Y'),
                'city' => $request->city,
                'created_at' => $request->created_at?->format('d/m/Y'),
            ]);

        return Inertia::render('PaintDevelopmentRequests/Index', [
            'requests' => $requests,
            'filters' => [
                'search' => request('search'),
                'status' => $status,
            ],
            'statusOptions' => $this->enumOptions(PaintDevelopmentRequestStatus::cases()),
            'can' => [
                'create' => $user?->can('create', PaintDevelopmentRequest::class) ?? false,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', PaintDevelopmentRequest::class);

        return Inertia::render('PaintDevelopmentRequests/Create');
    }

    public function store(StorePaintDevelopmentRequest $request): RedirectResponse
    {
        $paintRequest = $this->service->create(
            $request->validated(),
            $request->user(),
            $request->boolean('_draft', true),
        );

        $message = $paintRequest->status === PaintDevelopmentRequestStatus::Submitted
            ? __('Solicitud enviada para revisión.')
            : __('Solicitud de desarrollo guardada en borrador.');

        return redirect()->route('paint-development-requests.show', $paintRequest)
            ->with('success', $message);
    }

    public function show(PaintDevelopmentRequest $paintDevelopmentRequest): Response
    {
        $this->authorize('view', $paintDevelopmentRequest);

        $paintDevelopmentRequest->load(['creator', 'reviewer']);

        return Inertia::render('PaintDevelopmentRequests/Show', [
            'request' => $this->buildRequestData($paintDevelopmentRequest),
            'can' => [
                'update' => auth()->user()?->can('update', $paintDevelopmentRequest) ?? false,
                'exportPdf' => auth()->user()?->can('exportPdf', $paintDevelopmentRequest) ?? false,
                'updateStatus' => auth()->user()?->can('updateStatus', $paintDevelopmentRequest) ?? false,
                'submit' => auth()->user()?->can('update', $paintDevelopmentRequest)
                    && $paintDevelopmentRequest->status === PaintDevelopmentRequestStatus::Draft,
            ],
            'nextStatusOptions' => $this->enumOptions($paintDevelopmentRequest->status->nextTransitions()),
        ]);
    }

    public function edit(PaintDevelopmentRequest $paintDevelopmentRequest): Response
    {
        $this->authorize('update', $paintDevelopmentRequest);

        $paintDevelopmentRequest->load(['creator']);

        return Inertia::render('PaintDevelopmentRequests/Edit', [
            'request' => $this->buildRequestData($paintDevelopmentRequest),
        ]);
    }

    public function update(
        UpdatePaintDevelopmentRequest $request,
        PaintDevelopmentRequest $paintDevelopmentRequest
    ): RedirectResponse {
        $this->service->update(
            $paintDevelopmentRequest,
            $request->validated(),
            $request->boolean('_draft', true),
        );

        $message = $paintDevelopmentRequest->fresh()->status === PaintDevelopmentRequestStatus::Submitted
            ? __('Solicitud enviada para revisión.')
            : __('Solicitud de desarrollo actualizada.');

        return redirect()->route('paint-development-requests.show', $paintDevelopmentRequest)
            ->with('success', $message);
    }

    public function submit(PaintDevelopmentRequest $paintDevelopmentRequest): RedirectResponse
    {
        $this->authorize('update', $paintDevelopmentRequest);

        $this->service->submit($paintDevelopmentRequest);

        return redirect()->route('paint-development-requests.show', $paintDevelopmentRequest)
            ->with('success', __('Solicitud enviada para revisión.'));
    }

    public function updateStatus(
        UpdatePaintDevelopmentRequestStatus $request,
        PaintDevelopmentRequest $paintDevelopmentRequest
    ): RedirectResponse {
        $this->service->updateStatus($paintDevelopmentRequest, $request->validated());

        return redirect()->route('paint-development-requests.show', $paintDevelopmentRequest)
            ->with('success', __('Estado de la solicitud actualizado.'));
    }

    public function exportPdf(PaintDevelopmentRequest $paintDevelopmentRequest): \Illuminate\Http\Response
    {
        $this->authorize('exportPdf', $paintDevelopmentRequest);

        $paintDevelopmentRequest->load(['creator', 'reviewer']);
        $filename = 'SDR-'.($paintDevelopmentRequest->request_number).'.pdf';

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView('pdf.paint-development-request', [
            'request' => $this->buildRequestData($paintDevelopmentRequest),
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);

        $pdf->setPaper('letter');

        return $pdf->download($filename);
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

        return PaintDevelopmentRequestStatus::tryFrom($status)?->value;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRequestData(PaintDevelopmentRequest $request): array
    {
        return [
            'id' => $request->id,
            'request_number' => $request->request_number,
            'client_name' => $request->client_name,
            'project_name' => $request->project_name,
            'responsible' => $request->responsible,
            'city' => $request->city,
            'sample_due_date' => $request->sample_due_date?->format('Y-m-d'),
            'current_product' => $request->current_product,
            'context_payload' => $request->context_payload ?? [],
            'performance_payload' => $request->performance_payload ?? [],
            'application_payload' => $request->application_payload ?? [],
            'specifications_payload' => $request->specifications_payload ?? [],
            'schema_version' => $request->schema_version,
            'status' => $request->status->value,
            'status_label' => $request->status->label(),
            'review_notes' => $request->review_notes,
            'reviewed_at' => $request->reviewed_at?->format('d/m/Y H:i'),
            'reviewer' => $request->reviewer ? [
                'name' => $request->reviewer->name,
            ] : null,
            'created_at' => $request->created_at?->format('d/m/Y H:i'),
            'creator' => $request->creator ? [
                'name' => $request->creator->name,
                'email' => $request->creator->email,
            ] : null,
        ];
    }
}
