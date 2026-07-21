<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AlertType;
use App\Enums\ProductionOrderStatus;
use App\Models\Alert;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;

class AdminDashboardService
{
    public function __construct(
        private readonly AlertService $alertService,
    ) {}

    public function build(): array
    {
        $today = Carbon::today('America/Bogota')->format('Y-m-d');

        $totalUsers = User::query()->count();

        $totalProducts = Product::query()->count();

        $totalWarehouses = Warehouse::query()->count();

        $pendingOrders = ProductionOrder::query()
            ->where('status', ProductionOrderStatus::Pending)
            ->count();

        $activeOrders = ProductionOrder::query()
            ->where('status', ProductionOrderStatus::InProgress)
            ->count();

        $completedToday = ProductionOrder::query()
            ->where('status', ProductionOrderStatus::Completed)
            ->whereDate('completion_date', $today)
            ->count();

        $lowStockMaterials = Alert::query()
            ->where('is_resolved', false)
            ->where('type', AlertType::StockBajo)
            ->distinct('raw_material_id')
            ->count('raw_material_id');

        $expiringBatches = Alert::query()
            ->where('is_resolved', false)
            ->where('type', AlertType::VencimientoProximo)
            ->count();

        $recentOrders = ProductionOrder::query()
            ->with(['product:id,code'])
            ->where('status', '!=', ProductionOrderStatus::Cancelled)
            ->latest('id')
            ->limit(5)
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

        $recentAlerts = $this->alertService->recentUnresolved(5);

        return [
            'stats' => [
                'total_users' => $totalUsers,
                'total_products' => $totalProducts,
                'total_warehouses' => $totalWarehouses,
                'pending_orders' => $pendingOrders,
                'active_orders' => $activeOrders,
                'completed_today' => $completedToday,
                'unresolved_alerts' => $this->alertService->unresolvedCount(),
                'low_stock_materials' => $lowStockMaterials,
                'expiring_batches' => $expiringBatches,
            ],
            'recent_orders' => $recentOrders,
            'recent_alerts' => $recentAlerts,
            'alert_breakdown' => $this->alertService->unresolvedBreakdown(),
        ];
    }
}
