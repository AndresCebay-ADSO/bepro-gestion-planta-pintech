export type RecentOrder = {
    id: number;
    order_number: string;
    status: string;
    status_label: string;
    product_code: string | null;
    planned_date: string | null;
    completion_date: string | null;
};

/** Misma forma que la prop compartida `recentAlerts`. */
export type { RecentAlert } from '@/types/shared';

export type AlertBreakdown = {
    stock_bajo: number;
    vencimiento_proximo: number;
    variacion_precio: number;
    paint_development_request?: number;
};

export type RecentQuote = {
    id: number;
    reference_number: string;
    status: string;
    status_label: string;
    client_name: string;
    total: number | null;
    created_at: string | null;
};

export type RecentSalesOrder = {
    id: number;
    status: string;
    status_label: string;
    client_name: string;
    required_date: string | null;
    created_at: string | null;
};

export type DashboardStats = {
    total_users?: number;
    total_products?: number;
    total_warehouses?: number;
    pending_orders?: number;
    active_orders?: number;
    completed_today?: number;
    low_stock_materials?: number;
    expiring_batches?: number;
    pending_review_orders?: number;
    submitted_orders?: number;
    available_products?: number;
    active_quotes?: number;
    accepted_quotes?: number;
    total_clients?: number;
};
