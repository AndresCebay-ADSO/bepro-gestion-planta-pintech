<?php

namespace App\Http\Middleware;

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
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'job_title' => $user->job_title,
                    'email_verified_at' => $user->email_verified_at,
                    'signature_url' => $user->signature_url,
                    'role_names' => $user->getRoleNames()->values()->all(),
                ] : null,
            ],
            'flash' => [
                'message' => $request->session()->get('message'),
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'new_alerts' => $request->session()->pull('new_alerts', []),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'warehouseContext' => $warehouseContext,
            'unresolvedAlertsCount' => $user?->hasAnyRole(['admin', 'produccion'])
                ? $this->alertService->unresolvedCount()
                : 0,
            'recentAlerts' => $user?->hasAnyRole(['admin', 'produccion'])
                ? $this->alertService->recentUnresolved(5)
                : [],
        ];
    }
}
