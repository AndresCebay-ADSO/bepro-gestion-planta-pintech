<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Filters\AlertFilter;
use App\Http\Requests\Alerts\IndexAlertRequest;
use App\Models\Alert;
use App\Services\AlertService;
use App\Support\EnumOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    public function __construct(
        private readonly AlertService $alertService
    ) {}

    public function index(IndexAlertRequest $request): Response
    {
        $alerts = (new AlertFilter($request))
            ->apply(Alert::query())
            ->with([
                'rawMaterial:id,code',
                'batch:id,lot_number,expiry_date',
                'resolvedBy:id,name',
            ])
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
            'filters' => $request->validated(),
            'statusOptions' => [
                ['value' => 'active', 'label' => __('Activas')],
                ['value' => 'resolved', 'label' => __('Resueltas')],
            ],
            'typeOptions' => EnumOptions::for(AlertType::cases()),
            'severityOptions' => EnumOptions::for(AlertSeverity::cases()),
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
