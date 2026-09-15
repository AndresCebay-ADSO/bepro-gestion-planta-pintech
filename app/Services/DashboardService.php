<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AlertType;
use App\Enums\DashboardProfile;
use App\Enums\Permission;
use App\Enums\ProductionOrderStatus;
use App\Enums\QuotationStatus;
use App\Models\Alert;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;

class DashboardService
{
    /**
     * Cualquiera de estos permisos da acceso a la vista comercial.
     */
    private const COMMERCIAL_PERMISSIONS = [
        'quotations.view_own',
        'quotations.view_all',
        'sales_orders.view_own',
        'sales_orders.view_all',
    ];

    public function __construct(
        private readonly AlertService $alertService,
    ) {}

    /**
     * Construye los datos del dashboard. La vista y cada dato dependen de los permisos del usuario.
     */
    public function build(User $user): array
    {
        $profile = $this->resolveProfile($user);

        return [
            'profile' => $profile->value,
            ...match ($profile) {
                DashboardProfile::Admin => $this->buildForAdmin($user),
                DashboardProfile::Production => $this->buildForProduction($user),
                DashboardProfile::Plant => $this->buildForPlant(),
                DashboardProfile::Commercial => $this->buildForCommercial($user),
                DashboardProfile::None => ['stats' => []],
            },
        ];
    }

    /**
     * Elige la vista del dashboard: la primera cuyo permiso tenga el usuario.
     */
    public function resolveProfile(User $user): DashboardProfile
    {
        return match (true) {
            $user->can(Permission::UsersView->value) => DashboardProfile::Admin,
            $user->can(Permission::ProductionOrdersCreate->value) => DashboardProfile::Production,
            $user->can(Permission::ProductionOrdersView->value) => DashboardProfile::Plant,
            $user->canAny(self::COMMERCIAL_PERMISSIONS) => DashboardProfile::Commercial,
            default => DashboardProfile::None,
        };
    }

    private function buildForAdmin(User $user): array
    {
        $today = Carbon::today('America/Bogota')->format('Y-m-d');
        $canSeeOrders = $user->can(Permission::ProductionOrdersView->value);
        $canSeeAlerts = $user->can(Permission::AlertsView->value);

        $stats = [
            ...($user->can(Permission::UsersView->value) ? ['total_users' => User::query()->count()] : []),
            ...($user->can(Permission::ProductsView->value) ? ['total_products' => Product::query()->count()] : []),
            ...($user->can(Permission::WarehousesView->value) ? ['total_warehouses' => Warehouse::query()->count()] : []),
            ...($canSeeOrders ? [
                'pending_orders' => $this->pendingOrdersCount(),
                'active_orders' => $this->activeOrdersCount(),
                'completed_today' => $this->completedTodayCount($today),
            ] : []),
            ...($canSeeAlerts ? ['unresolved_alerts' => $this->alertService->unresolvedCount()] : []),
            ...$this->stockStats($user),
        ];

        return [
            'stats' => $stats,
            ...($canSeeOrders ? ['recent_orders' => $this->recentProductionOrders(5)] : []),
            ...$this->alertBlock($user),
        ];
    }

    private function buildForProduction(User $user): array
    {
        $today = Carbon::today('America/Bogota')->format('Y-m-d');
        $canSeeOrders = $user->can(Permission::ProductionOrdersView->value);

        $stats = [
            ...($canSeeOrders ? [
                'pending_orders' => $this->pendingOrdersCount(),
                'active_orders' => $this->activeOrdersCount(),
                'pending_review_orders' => $this->pendingReviewOrdersCount(),
                'completed_today' => $this->completedTodayCount($today),
            ] : []),
            ...($user->can(Permission::AlertsView->value) ? ['unresolved_alerts' => $this->alertService->unresolvedCount()] : []),
            ...$this->stockStats($user),
        ];

        return [
            'stats' => $stats,
            ...($canSeeOrders ? ['recent_orders' => $this->recentProductionOrders(5)] : []),
            ...$this->alertBlock($user),
        ];
    }

    /**
     * Vista de planta: su permiso de entrada (production_orders.view) cubre todos sus datos.
     */
    private function buildForPlant(): array
    {
        $today = Carbon::today('America/Bogota')->format('Y-m-d');

        $stats = [
            'pending_orders' => $this->pendingOrdersCount(),
            'active_orders' => $this->activeOrdersCount(),
            'submitted_orders' => $this->pendingReviewOrdersCount(),
            'completed_today' => $this->completedTodayCount($today),
        ];

        return [
            'stats' => $stats,
            'recent_orders' => $this->recentActiveProductionOrders(8),
        ];
    }

    /**
     * Vista comercial: cada dato se calcula solo con su permiso y respeta view_own / view_all (scopes visibleTo).
     */
    private function buildForCommercial(User $user): array
    {
        $canSeeQuotes = $user->canAny([Permission::QuotationsViewOwn->value, Permission::QuotationsViewAll->value]);
        $canSeeSalesOrders = $user->canAny([Permission::SalesOrdersViewOwn->value, Permission::SalesOrdersViewAll->value]);

        return [
            'stats' => [
                ...($user->can(Permission::ProductsView->value)
                    ? ['available_products' => Product::query()->where('is_active', true)->count()]
                    : []),
                ...($canSeeQuotes ? $this->quotationStats($user) : []),
                ...($canSeeSalesOrders
                    ? ['pending_orders' => SalesOrder::query()->visibleTo($user)->pending()->count()]
                    : []),
                ...($user->can(Permission::ClientsView->value) ? ['total_clients' => Client::query()->count()] : []),
            ],
            ...($canSeeQuotes ? ['recent_quotes' => $this->recentQuotes($user)] : []),
            ...($canSeeSalesOrders ? ['recent_sales_orders' => $this->recentSalesOrders($user)] : []),
        ];
    }

    /**
     * @return array{active_quotes: int, accepted_quotes: int}
     */
    private function quotationStats(User $user): array
    {
        return [
            'active_quotes' => Quotation::query()
                ->visibleTo($user)
                ->whereIn('status', [QuotationStatus::Draft->value, QuotationStatus::Sent->value])
                ->count(),
            'accepted_quotes' => Quotation::query()
                ->visibleTo($user)
                ->where('status', QuotationStatus::Accepted->value)
                ->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentQuotes(User $user): array
    {
        return Quotation::query()
            ->with('client:id,business_name')
            ->visibleTo($user)
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (Quotation $quotation): array => [
                'id' => $quotation->id,
                'reference_number' => $quotation->quotation_number,
                'status' => $quotation->status->value,
                'status_label' => $quotation->status->label(),
                'client_name' => $quotation->client?->business_name ?? 'Sin cliente',
                'total' => (float) $quotation->total,
                'created_at' => $quotation->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentSalesOrders(User $user): array
    {
        return SalesOrder::query()
            ->with('client:id,business_name')
            ->visibleTo($user)
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (SalesOrder $order): array => [
                'id' => $order->id,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'client_name' => $order->client?->business_name ?? 'Sin cliente',
                'required_date' => $order->required_date?->format('Y-m-d'),
                'created_at' => $order->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function stockStats(User $user): array
    {
        if (! $user->can(Permission::RawMaterialsView->value)) {
            return [];
        }

        return [
            'low_stock_materials' => $this->lowStockCount(),
            'expiring_batches' => $this->expiringBatchesCount(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function alertBlock(User $user): array
    {
        if (! $user->can(Permission::AlertsView->value)) {
            return [];
        }

        return [
            'recent_alerts' => $this->alertService->recentUnresolved(5),
            'alert_breakdown' => $this->alertService->unresolvedBreakdown(),
        ];
    }

    private function pendingOrdersCount(): int
    {
        return ProductionOrder::query()
            ->where('status', ProductionOrderStatus::Pending)
            ->count();
    }

    private function activeOrdersCount(): int
    {
        return ProductionOrder::query()
            ->where('status', ProductionOrderStatus::InProgress)
            ->count();
    }

    private function pendingReviewOrdersCount(): int
    {
        return ProductionOrder::query()
            ->where('status', ProductionOrderStatus::PendingReview)
            ->count();
    }

    private function completedTodayCount(string $today): int
    {
        return ProductionOrder::query()
            ->where('status', ProductionOrderStatus::Completed)
            ->whereDate('completion_date', $today)
            ->count();
    }

    private function lowStockCount(): int
    {
        return Alert::query()
            ->where('is_resolved', false)
            ->where('type', AlertType::StockBajo)
            ->distinct('raw_material_id')
            ->count('raw_material_id');
    }

    private function expiringBatchesCount(): int
    {
        return Alert::query()
            ->where('is_resolved', false)
            ->where('type', AlertType::VencimientoProximo)
            ->count();
    }

    /**
     * @return list<array{id: int, order_number: string, status: string, status_label: string, product_code: string|null, planned_date: string|null, completion_date: string|null}>
     */
    private function recentProductionOrders(int $limit): array
    {
        return ProductionOrder::query()
            ->with(['product:id,code'])
            ->where('status', '!=', ProductionOrderStatus::Cancelled)
            ->latest('id')
            ->limit($limit)
            ->get(['id', 'order_number', 'status', 'product_id', 'planned_date', 'completion_date'])
            ->map(fn (ProductionOrder $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'product_code' => $order->product?->code,
                'planned_date' => $order->planned_date?->format('Y-m-d'),
                'completion_date' => $order->completion_date?->format('Y-m-d'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, order_number: string, status: string, status_label: string, product_code: string|null, planned_date: string|null, completion_date: string|null}>
     */
    private function recentActiveProductionOrders(int $limit): array
    {
        return ProductionOrder::query()
            ->with(['product:id,code'])
            ->whereIn('status', [
                ProductionOrderStatus::Pending,
                ProductionOrderStatus::InProgress,
                ProductionOrderStatus::PendingReview,
            ])
            ->latest('id')
            ->limit($limit)
            ->get(['id', 'order_number', 'status', 'product_id', 'planned_date', 'completion_date'])
            ->map(fn (ProductionOrder $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'product_code' => $order->product?->code,
                'planned_date' => $order->planned_date?->format('Y-m-d'),
                'completion_date' => $order->completion_date?->format('Y-m-d'),
            ])
            ->values()
            ->all();
    }
}
