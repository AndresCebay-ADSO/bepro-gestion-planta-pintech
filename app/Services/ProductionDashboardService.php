<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AlertType;
use App\Enums\ProductionOrderStatus;
use App\Models\Alert;
use App\Models\ProductionOrder;
use Illuminate\Support\Carbon;

class ProductionDashboardService
{
    public function __construct(
        private readonly AlertService $alertService,
    ) {}

    /**
     * @return array{
     *     stats: array{
     *     pending_orders: int,
     *     active_orders: int,
     *     pending_review_orders: int,
     *     completed_today: int,
     *         unresolved_alerts: int,
     *         low_stock_materials: int,
     *         expiring_batches: int
     *     },
     *     recent_orders: list<array{
     *         id: int,
     *         order_number: string,
     *         status: string,
     *         status_label: string,
     *         product_code: string|null,
     *         planned_date: string|null,
     *         completion_date: string|null
     *     }>,
     *     recent_alerts: list<array{
     *         id: int,
     *         type: string,
     *         type_label: string,
     *         severity: string,
     *         severity_label: string,
     *         message: string,
     *         created_at: string|null,
     *         raw_material_code: string|null
     *     }>,
     *     alert_breakdown: array{
     *         stock_bajo: int,
     *         vencimiento_proximo: int,
     *         variacion_precio: int
     *     }
     * }
     */
    public function build(): array
    {
        $today = Carbon::today('America/Bogota')->format('Y-m-d');

        $pendingOrders = ProductionOrder::query()
            ->where('status', ProductionOrderStatus::Pending)
            ->count();

        $activeOrders = ProductionOrder::query()
            ->where('status', ProductionOrderStatus::InProgress)
            ->count();

        $pendingReviewOrders = ProductionOrder::query()
            ->where('status', ProductionOrderStatus::PendingReview)
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
                'pending_orders' => $pendingOrders,
                'active_orders' => $activeOrders,
                'pending_review_orders' => $pendingReviewOrders,
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
