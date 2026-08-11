<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AlertType;
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
    public function __construct(
        private readonly AlertService $alertService,
    ) {}

    /**
     * Construye los datos del dashboard según el rol del usuario.
     */
    public function build(User $user): array
    {
        $role = $user->getRoleNames()->first();

        return match ($role) {
            'admin' => $this->buildForAdmin(),
            'produccion' => $this->buildForProduction(),
            'operador' => $this->buildForOperator(),
            'comercial' => $this->buildForComercial($user),
            default => throw new \LogicException("Dashboard: rol no soportado: {$role}"),
        };
    }

    private function buildForAdmin(): array
    {
        $today = Carbon::today('America/Bogota')->format('Y-m-d');

        $stats = [
            'total_users' => User::query()->count(),
            'total_products' => Product::query()->count(),
            'total_warehouses' => Warehouse::query()->count(),
            'pending_orders' => $this->pendingOrdersCount(),
            'active_orders' => $this->activeOrdersCount(),
            'completed_today' => $this->completedTodayCount($today),
            'unresolved_alerts' => $this->alertService->unresolvedCount(),
            'low_stock_materials' => $this->lowStockCount(),
            'expiring_batches' => $this->expiringBatchesCount(),
        ];

        return [
            'stats' => $stats,
            'recent_orders' => $this->recentProductionOrders(5),
            'recent_alerts' => $this->alertService->recentUnresolved(5),
            'alert_breakdown' => $this->alertService->unresolvedBreakdown(),
        ];
    }

    private function buildForProduction(): array
    {
        $today = Carbon::today('America/Bogota')->format('Y-m-d');

        $stats = [
            'pending_orders' => $this->pendingOrdersCount(),
            'active_orders' => $this->activeOrdersCount(),
            'pending_review_orders' => $this->pendingReviewOrdersCount(),
            'completed_today' => $this->completedTodayCount($today),
            'unresolved_alerts' => $this->alertService->unresolvedCount(),
            'low_stock_materials' => $this->lowStockCount(),
            'expiring_batches' => $this->expiringBatchesCount(),
        ];

        return [
            'stats' => $stats,
            'recent_orders' => $this->recentProductionOrders(5),
            'recent_alerts' => $this->alertService->recentUnresolved(5),
            'alert_breakdown' => $this->alertService->unresolvedBreakdown(),
        ];
    }

    private function buildForOperator(): array
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

    private function buildForComercial(User $user): array
    {
        $userId = $user->id;

        $activeQuotes = Quotation::query()
            ->where('created_by', $userId)
            ->whereIn('status', [QuotationStatus::Draft->value, QuotationStatus::Sent->value])
            ->count();

        $acceptedQuotes = Quotation::query()
            ->where('created_by', $userId)
            ->where('status', QuotationStatus::Accepted->value)
            ->count();

        $pendingOrders = SalesOrder::query()
            ->where('created_by', $userId)
            ->pending()
            ->count();

        $recentQuotes = Quotation::query()
            ->with('client:id,business_name')
            ->where('created_by', $userId)
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

        $recentOrders = SalesOrder::query()
            ->with('client:id,business_name')
            ->where('created_by', $userId)
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

        return [
            'stats' => [
                'available_products' => Product::query()->where('is_active', true)->count(),
                'active_quotes' => $activeQuotes,
                'accepted_quotes' => $acceptedQuotes,
                'pending_orders' => $pendingOrders,
                'total_clients' => Client::query()->count(),
            ],
            'recent_quotes' => $recentQuotes,
            'recent_sales_orders' => $recentOrders,
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
