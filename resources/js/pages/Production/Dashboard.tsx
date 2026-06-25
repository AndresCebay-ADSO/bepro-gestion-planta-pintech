import { Head, Link } from '@inertiajs/react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import {
    AlertTriangle,
    ArrowRight,
    BellRing,
    Boxes,
    CheckCircle2,
    ClipboardList,
    Factory,
    PackageX,
    Send,
    Timer,
    TrendingUp,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index as alertsIndex } from '@/routes/alerts';
import { index as inventoryMovementsIndex } from '@/routes/inventory-movements';
import { index as productionOrdersIndex } from '@/routes/production-orders';
import { show as productionOrderShow } from '@/routes/production-orders';
import { index as rawMaterialsIndex } from '@/routes/raw-materials';

type DashboardStats = {
    pending_orders: number;
    active_orders: number;
    pending_review_orders: number;
    completed_today: number;
    unresolved_alerts: number;
    low_stock_materials: number;
    expiring_batches: number;
};

type RecentOrder = {
    id: number;
    order_number: string;
    status: string;
    status_label: string;
    product_code: string | null;
    planned_date: string | null;
    completion_date: string | null;
};

type RecentAlert = {
    id: number;
    type: string;
    type_label: string;
    severity: string;
    severity_label: string;
    message: string;
    created_at: string | null;
    raw_material_code: string | null;
};

type AlertBreakdown = {
    stock_bajo: number;
    vencimiento_proximo: number;
    variacion_precio: number;
};

interface ProductionDashboardProps {
    role: string;
    userName: string;
    stats: DashboardStats;
    recent_orders: RecentOrder[];
    recent_alerts: RecentAlert[];
    alert_breakdown: AlertBreakdown;
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

function alertSeverityClass(severity: string): string {
    switch (severity) {
        case 'alta':
            return 'border-red-500/30 bg-red-500/10';
        case 'media':
            return 'border-amber-500/30 bg-amber-500/10';
        default:
            return 'border-slate-500/30 bg-slate-500/10';
    }
}

export default function ProductionDashboard({
    role,
    userName,
    stats,
    recent_orders,
    recent_alerts,
    alert_breakdown,
}: ProductionDashboardProps) {
    return (
        <div className="min-h-screen bg-slate-50/50 dark:bg-slate-950">
            <Head title="Panel de Producción" />

            <div className="relative overflow-hidden bg-slate-900 px-8 py-12 text-white">
                <div className="absolute inset-0 bg-linear-to-br from-orange-600/20 to-transparent" />
                <div className="relative mx-auto max-w-6xl">
                    <div className="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
                        <div className="flex items-start gap-4">
                            <div className="rounded-xl bg-orange-600 p-3 shadow-lg shadow-orange-600/20">
                                <Factory className="h-8 w-8" />
                            </div>
                            <div>
                                <h1 className="text-3xl font-bold tracking-tight md:text-4xl">
                                    Centro de Producción
                                </h1>
                                <p className="mt-1 text-slate-400">
                                    Hola, {userName}. Resumen operativo de
                                    planta.
                                </p>
                            </div>
                        </div>
                        <Badge className="w-fit border-orange-600/30 bg-orange-600/20 text-orange-300">
                            {role.toUpperCase()}
                        </Badge>
                    </div>
                </div>
            </div>

            <div className="mx-auto -mt-6 max-w-6xl space-y-8 px-8 pb-12">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Card className="border-none shadow-lg">
                        <CardContent className="flex items-center gap-4 p-5">
                            <div className="rounded-full bg-slate-100 p-3 text-slate-600 dark:bg-slate-900">
                                <Timer className="h-5 w-5" />
                            </div>
                            <div>
                                <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    OP pendientes
                                </p>
                                <p className="text-3xl font-bold">
                                    {stats.pending_orders}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-none shadow-lg">
                        <CardContent className="flex items-center gap-4 p-5">
                            <div className="rounded-full bg-orange-100 p-3 text-orange-600">
                                <ClipboardList className="h-5 w-5" />
                            </div>
                            <div>
                                <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    OP en proceso
                                </p>
                                <p className="text-3xl font-bold">
                                    {stats.active_orders}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-none shadow-lg">
                        <CardContent className="flex items-center gap-4 p-5">
                            <div className="rounded-full bg-emerald-100 p-3 text-emerald-600">
                                <CheckCircle2 className="h-5 w-5" />
                            </div>
                            <div>
                                <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    Completadas hoy
                                </p>
                                <p className="text-3xl font-bold">
                                    {stats.completed_today}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-none shadow-lg">
                        <CardContent className="flex items-center gap-4 p-5">
                            <div className="rounded-full bg-blue-100 p-3 text-blue-600">
                                <Send className="h-5 w-5" />
                            </div>
                            <div>
                                <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    OP en revisión
                                </p>
                                <p className="text-3xl font-bold">
                                    {stats.pending_review_orders}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-none shadow-lg">
                        <CardContent className="flex items-center gap-4 p-5">
                            <div className="rounded-full bg-red-100 p-3 text-red-600">
                                <BellRing className="h-5 w-5" />
                            </div>
                            <div>
                                <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    Alertas activas
                                </p>
                                <p className="text-3xl font-bold">
                                    {stats.unresolved_alerts}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-none shadow-lg">
                        <CardContent className="flex items-center gap-4 p-5">
                            <div className="rounded-full bg-amber-100 p-3 text-amber-600">
                                <PackageX className="h-5 w-5" />
                            </div>
                            <div>
                                <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    MP stock bajo
                                </p>
                                <p className="text-3xl font-bold">
                                    {stats.low_stock_materials}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-none shadow-lg">
                        <CardContent className="flex items-center gap-4 p-5">
                            <div className="rounded-full bg-purple-100 p-3 text-purple-600">
                                <AlertTriangle className="h-5 w-5" />
                            </div>
                            <div>
                                <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    Lotes por vencer
                                </p>
                                <p className="text-3xl font-bold">
                                    {stats.expiring_batches}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <Card className="border-none shadow-lg">
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle>Órdenes recientes</CardTitle>
                                <Button variant="ghost" size="sm" asChild>
                                    <Link href={productionOrdersIndex().url}>
                                        Ver todas
                                        <ArrowRight className="ml-1 h-4 w-4" />
                                    </Link>
                                </Button>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {recent_orders.length === 0 ? (
                                    <p className="py-6 text-center text-sm text-muted-foreground">
                                        No hay órdenes de producción
                                        registradas.
                                    </p>
                                ) : (
                                    recent_orders.map((order) => (
                                        <Link
                                            key={order.id}
                                            href={productionOrderShow.url(
                                                order.id,
                                            )}
                                            className="flex items-center justify-between rounded-lg border border-border p-4 transition hover:bg-muted/40"
                                        >
                                            <div className="space-y-1">
                                                <p className="font-mono font-semibold">
                                                    {order.order_number}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {order.product_code ??
                                                        'Sin producto'}
                                                    {order.planned_date
                                                        ? ` · Plan ${order.planned_date}`
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

                        <Card className="border-none shadow-lg">
                            <CardHeader>
                                <CardTitle>Accesos rápidos</CardTitle>
                            </CardHeader>
                            <CardContent className="grid grid-cols-1 gap-3 md:grid-cols-3">
                                <Button
                                    asChild
                                    variant="outline"
                                    className="h-auto justify-start py-4"
                                >
                                    <Link href={productionOrdersIndex().url}>
                                        <ClipboardList className="mr-2 h-4 w-4" />
                                        Órdenes
                                    </Link>
                                </Button>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="h-auto justify-start py-4"
                                >
                                    <Link href={rawMaterialsIndex().url}>
                                        <Boxes className="mr-2 h-4 w-4" />
                                        Materias primas
                                    </Link>
                                </Button>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="h-auto justify-start py-4"
                                >
                                    <Link href={inventoryMovementsIndex().url}>
                                        <TrendingUp className="mr-2 h-4 w-4" />
                                        Movimientos
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-6">
                        <Card className="border-none bg-slate-900 text-white shadow-2xl">
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <BellRing className="h-5 w-5 text-orange-400" />
                                    Alertas activas
                                </CardTitle>
                                <Button
                                    size="sm"
                                    variant="secondary"
                                    asChild
                                    className="bg-white/10 text-white hover:bg-white/20"
                                >
                                    <Link href={alertsIndex().url}>
                                        Ver panel
                                    </Link>
                                </Button>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-3 gap-2 text-center text-xs">
                                    <div className="rounded-lg bg-white/5 p-2">
                                        <p className="text-2xl font-bold">
                                            {alert_breakdown.stock_bajo}
                                        </p>
                                        <p className="text-slate-400">Stock</p>
                                    </div>
                                    <div className="rounded-lg bg-white/5 p-2">
                                        <p className="text-2xl font-bold">
                                            {
                                                alert_breakdown.vencimiento_proximo
                                            }
                                        </p>
                                        <p className="text-slate-400">Vence</p>
                                    </div>
                                    <div className="rounded-lg bg-white/5 p-2">
                                        <p className="text-2xl font-bold">
                                            {alert_breakdown.variacion_precio}
                                        </p>
                                        <p className="text-slate-400">Precio</p>
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    {recent_alerts.length === 0 ? (
                                        <p className="rounded-lg border border-white/10 bg-white/5 p-4 text-sm text-slate-400">
                                            No hay alertas activas en este
                                            momento.
                                        </p>
                                    ) : (
                                        recent_alerts.map((alert) => (
                                            <div
                                                key={alert.id}
                                                className={`rounded-lg border p-3 text-sm ${alertSeverityClass(alert.severity)}`}
                                            >
                                                <p className="mb-1 text-xs font-semibold tracking-wide text-slate-300 uppercase">
                                                    {alert.type_label} ·{' '}
                                                    {alert.severity_label}
                                                </p>
                                                <p className="leading-snug text-white">
                                                    {alert.message}
                                                </p>
                                                {alert.created_at && (
                                                    <p className="mt-2 text-xs text-slate-400">
                                                        {format(
                                                            new Date(
                                                                alert.created_at,
                                                            ),
                                                            'dd MMM HH:mm',
                                                            { locale: es },
                                                        )}
                                                    </p>
                                                )}
                                            </div>
                                        ))
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </div>
    );
}
