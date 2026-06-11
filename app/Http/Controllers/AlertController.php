<?php

namespace App\Http\Controllers;

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Models\Alert;
use App\Services\AlertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    public function __construct(
        private readonly AlertService $alertService
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Alert::class);

        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['active', 'resolved', 'all'])],
            'type' => ['nullable', 'string', Rule::in(array_column(AlertType::cases(), 'value'))],
            'severity' => ['nullable', 'string', Rule::in(array_column(AlertSeverity::cases(), 'value'))],
        ]);

        $status = $validated['status'] ?? 'active';
        $type = $validated['type'] ?? 'all';
        $severity = $validated['severity'] ?? 'all';

        $alerts = Alert::query()
            ->with([
                'rawMaterial:id,code',
                'batch:id,lot_number,expiry_date',
                'resolvedBy:id,name',
            ])
            ->when($status === 'active', fn ($query) => $query->where('is_resolved', false))
            ->when($status === 'resolved', fn ($query) => $query->where('is_resolved', true))
            ->when($type !== 'all', fn ($query) => $query->where('type', $type))
            ->when($severity !== 'all', fn ($query) => $query->where('severity', $severity))
            ->latest('id')
            ->paginate(20)
            ->onEachSide(1)
            ->withQueryString()
            ->through(function (Alert $alert) use ($request): array {
                return [
                    'id' => $alert->id,
                    'type' => $alert->type->value,
                    'type_label' => $alert->type->label(),
                    'severity' => $alert->severity->value,
                    'severity_label' => $alert->severity->label(),
                    'message' => $alert->message,
                    'is_resolved' => $alert->is_resolved,
                    'created_at' => $alert->created_at?->toIso8601String(),
                    'resolved_at' => $alert->resolved_at?->toIso8601String(),
                    'raw_material' => $alert->rawMaterial ? [
                        'id' => $alert->rawMaterial->id,
                        'code' => $alert->rawMaterial->code,
                    ] : null,
                    'batch' => $alert->batch ? [
                        'id' => $alert->batch->id,
                        'lot_number' => $alert->batch->lot_number,
                        'expiry_date' => $alert->batch->expiry_date?->format('Y-m-d'),
                    ] : null,
                    'resolved_by' => $alert->resolvedBy ? [
                        'id' => $alert->resolvedBy->id,
                        'name' => $alert->resolvedBy->name,
                    ] : null,
                    'can' => [
                        'resolve' => ! $alert->is_resolved
                            && Gate::forUser($request->user())->allows('resolve', $alert),
                    ],
                ];
            });

        return Inertia::render('Alerts/Index', [
            'alerts' => $alerts,
            'filters' => [
                'status' => $status,
                'type' => $type,
                'severity' => $severity,
            ],
            'options' => [
                'types' => collect(AlertType::cases())->map(fn (AlertType $case) => [
                    'value' => $case->value,
                    'label' => $case->label(),
                ])->values()->all(),
                'severities' => collect(AlertSeverity::cases())->map(fn (AlertSeverity $case) => [
                    'value' => $case->value,
                    'label' => $case->label(),
                ])->values()->all(),
            ],
            'stats' => [
                'unresolved_count' => $this->alertService->unresolvedCount(),
            ],
        ]);
    }

    public function resolve(Request $request, Alert $alert): RedirectResponse
    {
        $this->authorize('resolve', $alert);

        $this->alertService->resolve($alert, (int) $request->user()->id);

        return back()->with('success', __('Alerta marcada como resuelta.'));
    }
}
