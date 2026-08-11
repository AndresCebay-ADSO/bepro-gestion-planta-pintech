import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { RecentOrder } from './types';
import { formatBackendDate } from './utils';

interface RecentOrdersListProps {
    orders: RecentOrder[];
    title?: string;
    emptyMessage?: string;
    viewAllHref?: string;
    showHref?: (id: number) => string;
}

function orderStatusClass(status: string): string {
    switch (status) {
        case 'pending':
            return 'bg-slate-500/15 text-slate-700 dark:text-slate-300';
        case 'in_progress':
            return 'bg-orange-500/15 text-orange-700 dark:text-orange-300';
        case 'pending_review':
            return 'bg-blue-500/15 text-blue-700 dark:text-blue-300';
        case 'completed':
            return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300';
        default:
            return 'bg-slate-500/15 text-slate-700 dark:text-slate-300';
    }
}

export function RecentOrdersList({
    orders,
    title = 'Órdenes recientes',
    emptyMessage = 'No hay órdenes de producción registradas.',
    viewAllHref,
    showHref,
}: RecentOrdersListProps) {
    return (
        <Card className="border-none shadow-lg">
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle>{title}</CardTitle>
                {viewAllHref && (
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={viewAllHref}>
                            Ver todas
                            <ArrowRight className="ml-1 h-4 w-4" />
                        </Link>
                    </Button>
                )}
            </CardHeader>
            <CardContent className="space-y-3">
                {orders.length === 0 ? (
                    <p className="py-6 text-center text-sm text-muted-foreground">
                        {emptyMessage}
                    </p>
                ) : (
                    orders.map((order) => (
                        <Link
                            key={order.id}
                            href={showHref ? showHref(order.id) : '#'}
                            className="flex items-center justify-between rounded-lg border border-border p-4 transition hover:bg-muted/40"
                        >
                            <div className="space-y-1">
                                <p className="font-mono font-semibold">
                                    {order.order_number}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {order.product_code ?? 'Sin producto'}
                                    {order.planned_date
                                        ? ` · Plan: ${formatBackendDate(order.planned_date)}`
                                        : ''}
                                </p>
                            </div>
                            <span
                                className={`rounded-full px-2.5 py-1 text-xs font-medium ${orderStatusClass(order.status)}`}
                            >
                                {order.status_label}
                            </span>
                        </Link>
                    ))
                )}
            </CardContent>
        </Card>
    );
}

export function RecentOrdersCompact({
    orders,
    title = 'Órdenes activas',
    emptyMessage = 'No hay órdenes pendientes de trabajo en planta.',
    viewAllHref,
    showHref,
}: RecentOrdersListProps) {
    return (
        <Card className="border-none shadow-lg">
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle className="flex items-center gap-2 text-lg">
                    {title}
                </CardTitle>
                {viewAllHref && (
                    <Button asChild variant="outline" size="sm">
                        <Link href={viewAllHref}>Ver todas</Link>
                    </Button>
                )}
            </CardHeader>
            <CardContent className="space-y-3">
                {orders.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {emptyMessage}
                    </p>
                ) : (
                    orders.map((order) => (
                        <Link
                            key={order.id}
                            href={showHref ? showHref(order.id) : '#'}
                            className="flex items-center justify-between rounded-lg border border-border/60 p-4 transition-colors hover:bg-muted/40"
                        >
                            <div>
                                <p className="font-mono font-semibold">
                                    {order.order_number}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {order.product_code ?? 'Sin producto'} •
                                    Plan:{" "}
                                    {order.planned_date
                                        ? formatBackendDate(order.planned_date)
                                        : '—'}
                                </p>
                            </div>
                            <span
                                className={`rounded-full px-2.5 py-1 text-xs font-medium ${orderStatusClass(order.status)}`}
                            >
                                {order.status_label}
                            </span>
                        </Link>
                    ))
                )}
            </CardContent>
        </Card>
    );
}
