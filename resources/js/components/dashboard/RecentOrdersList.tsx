import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

import { StatusBadge } from '@/components/status-badge';
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
                                    {order.product_name ?? 'Sin producto'}
                                    {order.planned_date
                                        ? ` · Plan: ${formatBackendDate(order.planned_date)}`
                                        : ''}
                                </p>
                            </div>
                            <StatusBadge
                                enum="ProductionOrderStatus"
                                value={order.status}
                            />
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
                                    {order.product_name ?? 'Sin producto'} •
                                    Plan:{' '}
                                    {order.planned_date
                                        ? formatBackendDate(order.planned_date)
                                        : '—'}
                                </p>
                            </div>
                            <StatusBadge
                                enum="ProductionOrderStatus"
                                value={order.status}
                            />
                        </Link>
                    ))
                )}
            </CardContent>
        </Card>
    );
}
