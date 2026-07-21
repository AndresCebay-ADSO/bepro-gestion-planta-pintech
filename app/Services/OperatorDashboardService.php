<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductionOrderStatus;
use App\Models\ProductionOrder;
use Illuminate\Support\Carbon;

class OperatorDashboardService
{
    /**
     * @return array{
     *     stats: array{
     *         pending_orders: int,
     *         active_orders: int,
     *         submitted_orders: int,
     *         completed_today: int
     *     },
     *     recent_orders: list<array{
     *         id: int,
     *         order_number: string,
     *         status: string,
     *         status_label: string,
     *         product_code: string|null,
     *         planned_date: string|null,
     *         completion_date: string|null
     *     }>
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

        $submittedOrders = ProductionOrder::query()
            ->where('status', ProductionOrderStatus::PendingReview)
            ->count();

        $completedToday = ProductionOrder::query()
            ->where('status', ProductionOrderStatus::Completed)
            ->whereDate('completion_date', $today)
            ->count();

        $recentOrders = ProductionOrder::query()
            ->with(['product:id,code'])
            ->whereIn('status', [
                ProductionOrderStatus::Pending,
                ProductionOrderStatus::InProgress,
                ProductionOrderStatus::PendingReview,
            ])
            ->latest('id')
            ->limit(8)
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

        return [
            'stats' => [
                'pending_orders' => $pendingOrders,
                'active_orders' => $activeOrders,
                'submitted_orders' => $submittedOrders,
                'completed_today' => $completedToday,
            ],
            'recent_orders' => $recentOrders,
        ];
    }
}
