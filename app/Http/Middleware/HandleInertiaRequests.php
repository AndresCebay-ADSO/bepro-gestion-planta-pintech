<?php

namespace App\Http\Middleware;

use App\Enums\AlertType;
use App\Enums\Permission;
use App\Models\Alert;
use App\Models\User;
use App\Services\AlertService;
use App\Services\WarehouseContextService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(
        private readonly WarehouseContextService $warehouseContextService,
        private readonly AlertService $alertService,
    ) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $warehouseContext = null;

        if ($user) {
            $availableWarehouses = $this->warehouseContextService->availableWarehouses($user);
            $currentWarehouse = $this->warehouseContextService->resolveCurrentWarehouse(
                $user,
                $request->session()->get('current_warehouse_id'),
            );

            if ($currentWarehouse) {
                $request->session()->put('current_warehouse_id', $currentWarehouse->id);
            }

            $warehouseContext = [
                'current' => $currentWarehouse ? [
                    'id' => $currentWarehouse->id,
                    'name' => $currentWarehouse->name,
                    'city' => $currentWarehouse->city,
                ] : null,
                'available' => $availableWarehouses
                    ->map(fn ($warehouse) => [
                        'id' => $warehouse->id,
                        'name' => $warehouse->name,
                        'city' => $warehouse->city,
                    ])
                    ->values()
                    ->all(),
            ];
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            // Closures: Inertia solo las evalúa al renderizar una página, no en los POST que redirigen ni en las descargas.
            'auth' => fn (): array => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'job_title' => $user->job_title,
                    'email_verified_at' => $user->email_verified_at,
                    'signature_url' => $user->signature_url,
                    'is_active' => (bool) $user->is_active,
                    'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
                ] : null,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'new_alerts' => $this->visibleNewAlerts($request, $user),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'warehouseContext' => $warehouseContext,
            'unresolvedAlertsCount' => fn (): int => $user?->can(Permission::AlertsView->value)
                ? $this->alertService->unresolvedCount($user)
                : 0,
            'recentAlerts' => fn (): array => $user?->can(Permission::AlertsView->value)
                ? $this->alertService->recentUnresolved($user, 5)
                : [],
        ];
    }

    /**
     * Alertas creadas en la petición anterior (notificación emergente), solo de los tipos que ve el usuario.
     * Se retiran de la sesión igualmente.
     *
     * @return array<int, mixed>
     */
    private function visibleNewAlerts(Request $request, ?User $user): array
    {
        $newAlerts = $request->session()->pull('new_alerts', []);
        $visibleTypes = array_map(fn (AlertType $type): string => $type->value, Alert::visibleTypesFor($user));

        return array_values(array_filter(
            is_array($newAlerts) ? $newAlerts : [],
            fn (mixed $alert): bool => is_array($alert) && in_array($alert['type'] ?? null, $visibleTypes, true),
        ));
    }
}
