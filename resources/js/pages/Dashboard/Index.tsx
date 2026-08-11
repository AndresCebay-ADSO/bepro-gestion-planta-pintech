import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    BellRing,
    Boxes,
    Building2,
    CheckCircle2,
    ClipboardList,
    Factory,
    FileText,
    PackageX,
    Send,
    Settings,
    ShieldCheck,
    ShoppingBag,
    ShoppingCart,
    Timer,
    TrendingUp,
    Users,
    WalletCards,
} from 'lucide-react';

import { DashboardHeader } from '@/components/dashboard/DashboardHeader';
import { QuickAccessGrid } from '@/components/dashboard/QuickAccessGrid';
import { RecentAlertsCard } from '@/components/dashboard/RecentAlertsCard';
import { RecentOrdersCompact, RecentOrdersList } from '@/components/dashboard/RecentOrdersList';
import { StatCard } from '@/components/dashboard/StatCard';
import type { AlertBreakdown, DashboardStats, RecentAlert, RecentOrder, RecentQuote, RecentSalesOrder } from '@/components/dashboard/types';
import { formatBackendDate } from '@/components/dashboard/utils';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index as alertsIndex } from '@/routes/alerts';
import { index as auditLogsIndex } from '@/routes/audit-logs';
import { index as clientsIndex } from '@/routes/clients';
import { index as inventoryMovementsIndex } from '@/routes/inventory-movements';
import { index as pricesIndex } from '@/routes/prices';
import { index as productionOrdersIndex, show as productionOrderShow } from '@/routes/production-orders';
import { index as productsIndex } from '@/routes/products';
import { index as quotationsIndex, show as quotationShow } from '@/routes/quotations';
import { index as rawMaterialsIndex } from '@/routes/raw-materials';
import { index as salesOrdersIndex, show as salesOrderShow } from '@/routes/sales-orders';
import { index as usersIndex } from '@/routes/users';
import { index as warehousesIndex } from '@/routes/warehouses';

interface DashboardProps {
    role: string;
    userName: string;
    stats: DashboardStats;
    recent_orders?: RecentOrder[];
    recent_alerts?: RecentAlert[];
    alert_breakdown?: AlertBreakdown;
    recent_quotes?: RecentQuote[];
    recent_sales_orders?: RecentSalesOrder[];
}

function quoteStatusClass(status: string): string {
    switch (status) {
        case 'draft':
            return 'bg-slate-500/15 text-slate-700 dark:text-slate-300';
        case 'sent':
            return 'bg-blue-500/15 text-blue-700 dark:text-blue-300';
        case 'accepted':
            return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300';
        case 'rejected':
            return 'bg-red-500/15 text-red-700 dark:text-red-300';
        default:
            return 'bg-slate-500/15 text-slate-700 dark:text-slate-300';
    }
}

function salesOrderStatusClass(status: string): string {
    switch (status) {
        case 'pending':
            return 'bg-yellow-500/15 text-yellow-700 dark:text-yellow-300';
        case 'in_progress':
            return 'bg-blue-500/15 text-blue-700 dark:text-blue-300';
        case 'ready':
            return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300';
        case 'delivered':
            return 'bg-purple-500/15 text-purple-700 dark:text-purple-300';
        case 'cancelled':
            return 'bg-red-500/15 text-red-700 dark:text-red-300';
        default:
            return 'bg-slate-500/15 text-slate-700 dark:text-slate-300';
    }
}

export default function Dashboard({
    role,
    userName,
    stats,
    recent_orders,
    recent_alerts,
    alert_breakdown,
    recent_quotes,
    recent_sales_orders,
}: DashboardProps) {

    return (
        <div className="min-h-screen bg-slate-50/50 dark:bg-slate-950">
            <Head title="Dashboard" />

            {role === 'admin' && (
                <>
                    <DashboardHeader
                        userName={userName}
                        role={role}
                        title="Panel de Administración"
                        subtitle="Hola, {name}. Resumen general del sistema."
                        icon={Settings}
                        iconBgClassName="bg-blue-600 shadow-blue-600/20"
                        badgeBorderClassName="border-blue-600/30"
                        badgeBgClassName="bg-blue-600/20"
                        badgeTextClassName="text-blue-300"
                    />
                    <div className="mx-auto -mt-6 max-w-6xl space-y-8 px-8 pb-12">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <StatCard icon={Users} label="Usuarios" value={stats.total_users ?? 0} iconClassName="bg-blue-100 text-blue-600" />
                            <StatCard icon={ShoppingBag} label="Productos" value={stats.total_products ?? 0} iconClassName="bg-emerald-100 text-emerald-600" />
                            <StatCard icon={Building2} label="Almacenes" value={stats.total_warehouses ?? 0} iconClassName="bg-violet-100 text-violet-600" />
                            <StatCard icon={Timer} label="OP pendientes" value={stats.pending_orders ?? 0} iconClassName="bg-slate-100 text-slate-600" />
                            <StatCard icon={ClipboardList} label="OP en proceso" value={stats.active_orders ?? 0} iconClassName="bg-orange-100 text-orange-600" />
                            <StatCard icon={CheckCircle2} label="Completadas hoy" value={stats.completed_today ?? 0} iconClassName="bg-emerald-100 text-emerald-600" />
                            <StatCard icon={BellRing} label="Alertas activas" value={stats.unresolved_alerts ?? 0} iconClassName="bg-red-100 text-red-600" />
                            <StatCard icon={PackageX} label="MP stock bajo" value={stats.low_stock_materials ?? 0} iconClassName="bg-amber-100 text-amber-600" />
                            <StatCard icon={AlertTriangle} label="Lotes por vencer" value={stats.expiring_batches ?? 0} iconClassName="bg-purple-100 text-purple-600" />
                        </div>
                        <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
                            <div className="space-y-6 lg:col-span-2">
                                <RecentOrdersList
                                    orders={recent_orders ?? []}
                                    viewAllHref={productionOrdersIndex().url}
                                    showHref={(id) => productionOrderShow(id).url}
                                />
                                <QuickAccessGrid
                                    items={[
                                        { label: 'Usuarios', href: usersIndex().url, icon: Users },
                                        { label: 'Productos', href: productsIndex().url, icon: ShoppingBag },
                                        { label: 'Almacenes', href: warehousesIndex().url, icon: Building2 },
                                        { label: 'Materias primas', href: rawMaterialsIndex().url, icon: Boxes },
                                        { label: 'Órdenes', href: productionOrdersIndex().url, icon: ClipboardList },
                                        { label: 'Alertas', href: alertsIndex().url, icon: BellRing },
                                        { label: 'Auditoría', href: auditLogsIndex().url, icon: ShieldCheck },
                                    ]}
                                />
                            </div>
                            <div className="space-y-6">
                                {recent_alerts && alert_breakdown && (
                                    <RecentAlertsCard
                                        alerts={recent_alerts}
                                        alert_breakdown={alert_breakdown}
                                        panelHref={alertsIndex().url}
                                    />
                                )}
                            </div>
                        </div>
                    </div>
                </>
            )}

            {role === 'produccion' && (
                <>
                    <DashboardHeader
                        userName={userName}
                        role={role}
                        title="Centro de Producción"
                        subtitle="Hola, {name}. Resumen operativo de planta."
                        icon={Factory}
                        iconBgClassName="bg-orange-600 shadow-orange-600/20"
                        badgeBorderClassName="border-orange-600/30"
                        badgeBgClassName="bg-orange-600/20"
                        badgeTextClassName="text-orange-300"
                    />
                    <div className="mx-auto -mt-6 max-w-6xl space-y-8 px-8 pb-12">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <StatCard icon={Timer} label="OP pendientes" value={stats.pending_orders ?? 0} iconClassName="bg-slate-100 text-slate-600 dark:bg-slate-900" />
                            <StatCard icon={ClipboardList} label="OP en proceso" value={stats.active_orders ?? 0} iconClassName="bg-orange-100 text-orange-600" />
                            <StatCard icon={CheckCircle2} label="Completadas hoy" value={stats.completed_today ?? 0} iconClassName="bg-emerald-100 text-emerald-600" />
                            <StatCard icon={Send} label="OP en revisión" value={stats.pending_review_orders ?? 0} iconClassName="bg-blue-100 text-blue-600" />
                            <StatCard icon={BellRing} label="Alertas activas" value={stats.unresolved_alerts ?? 0} iconClassName="bg-red-100 text-red-600" />
                            <StatCard icon={PackageX} label="MP stock bajo" value={stats.low_stock_materials ?? 0} iconClassName="bg-amber-100 text-amber-600" />
                            <StatCard icon={AlertTriangle} label="Lotes por vencer" value={stats.expiring_batches ?? 0} iconClassName="bg-purple-100 text-purple-600" />
                        </div>
                        <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
                            <div className="space-y-6 lg:col-span-2">
                                <RecentOrdersList
                                    orders={recent_orders ?? []}
                                    viewAllHref={productionOrdersIndex().url}
                                    showHref={(id) => productionOrderShow(id).url}
                                />
                                <QuickAccessGrid
                                    items={[
                                        { label: 'Órdenes', href: productionOrdersIndex().url, icon: ClipboardList },
                                        { label: 'Materias primas', href: rawMaterialsIndex().url, icon: Boxes },
                                        { label: 'Movimientos', href: inventoryMovementsIndex().url, icon: TrendingUp },
                                    ]}
                                />
                            </div>
                            <div className="space-y-6">
                                {recent_alerts && alert_breakdown && (
                                    <RecentAlertsCard
                                        alerts={recent_alerts}
                                        alert_breakdown={alert_breakdown}
                                        panelHref={alertsIndex().url}
                                    />
                                )}
                            </div>
                        </div>
                    </div>
                </>
            )}

            {role === 'operador' && (
                <>
                    <DashboardHeader
                        userName={userName}
                        role={role}
                        title="Panel de Planta"
                        subtitle="Hola, {name}. Órdenes asignadas para ejecución y precierre."
                        icon={Factory}
                        iconBgClassName="bg-blue-600 shadow-blue-600/20"
                        badgeBorderClassName="border-blue-600/30"
                        badgeBgClassName="bg-blue-600/20"
                        badgeTextClassName="text-blue-300"
                    />
                    <div className="mx-auto -mt-6 max-w-6xl space-y-8 px-8 pb-12">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <StatCard icon={Timer} label="Pendientes" value={stats.pending_orders ?? 0} iconClassName="bg-slate-100 text-slate-600 dark:bg-slate-900" />
                            <StatCard icon={ClipboardList} label="En proceso" value={stats.active_orders ?? 0} iconClassName="bg-orange-100 text-orange-600" />
                            <StatCard icon={Send} label="En revisión" value={stats.submitted_orders ?? 0} iconClassName="bg-blue-100 text-blue-600" />
                            <StatCard icon={CheckCircle2} label="Completadas hoy" value={stats.completed_today ?? 0} iconClassName="bg-emerald-100 text-emerald-600" />
                        </div>
                        <RecentOrdersCompact
                            orders={recent_orders ?? []}
                            viewAllHref={productionOrdersIndex().url}
                            showHref={(id) => productionOrderShow(id).url}
                        />
                    </div>
                </>
            )}

            {role === 'comercial' && (
                <>
                    <DashboardHeader
                        userName={userName}
                        role={role}
                        title="Panel Comercial"
                        subtitle="Hola, {name}. Resumen de cotizaciones, pedidos y clientes."
                        icon={WalletCards}
                        iconBgClassName="bg-emerald-600 shadow-emerald-600/20"
                        badgeBorderClassName="border-emerald-600/30"
                        badgeBgClassName="bg-emerald-600/20"
                        badgeTextClassName="text-emerald-300"
                    />
                    <div className="mx-auto -mt-6 max-w-6xl space-y-8 px-8 pb-12">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <StatCard icon={ShoppingBag} label="Productos disponibles" value={stats.available_products ?? 0} iconClassName="bg-blue-100 text-blue-600" />
                            <StatCard icon={FileText} label="Cotizaciones activas" value={stats.active_quotes ?? 0} iconClassName="bg-orange-100 text-orange-600" />
                            <StatCard icon={CheckCircle2} label="Cotizaciones aceptadas" value={stats.accepted_quotes ?? 0} iconClassName="bg-emerald-100 text-emerald-600" />
                            <StatCard icon={ShoppingCart} label="Pedidos pendientes" value={stats.pending_orders ?? 0} iconClassName="bg-amber-100 text-amber-600" />
                            <StatCard icon={Users} label="Total clientes" value={stats.total_clients ?? 0} iconClassName="bg-violet-100 text-violet-600" />
                        </div>
                        <div className="grid grid-cols-1 gap-8 lg:grid-cols-2">
                            <Card className="border-none shadow-lg">
                                <CardHeader className="flex flex-row items-center justify-between">
                                    <CardTitle>Cotizaciones recientes</CardTitle>
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link href={quotationsIndex().url}>
                                            Ver todas
                                            <ArrowRight className="ml-1 h-4 w-4" />
                                        </Link>
                                    </Button>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    {(recent_quotes ?? []).length === 0 ? (
                                        <p className="py-6 text-center text-sm text-muted-foreground">
                                            No hay cotizaciones registradas.
                                        </p>
                                    ) : (
                                        (recent_quotes ?? []).map((quote) => (
                                            <Link
                                                key={quote.id}
                                                href={quotationShow(quote.id).url}
                                                className="flex items-center justify-between rounded-lg border border-border p-4 transition hover:bg-muted/40"
                                            >
                                                <div className="space-y-1">
                                                    <p className="font-mono font-semibold">
                                                        {quote.reference_number}
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {quote.client_name}
                                                        {quote.total !== null
                                                            ? ` · ${new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 }).format(quote.total)}`
                                                            : ''}
                                                    </p>
                                                </div>
                                                <span
                                                    className={`rounded-full px-2.5 py-1 text-xs font-medium ${quoteStatusClass(quote.status)}`}
                                                >
                                                    {quote.status_label}
                                                </span>
                                            </Link>
                                        ))
                                    )}
                                </CardContent>
                            </Card>

                            <Card className="border-none shadow-lg">
                                <CardHeader className="flex flex-row items-center justify-between">
                                    <CardTitle>Pedidos recientes</CardTitle>
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link href={salesOrdersIndex().url}>
                                            Ver todos
                                            <ArrowRight className="ml-1 h-4 w-4" />
                                        </Link>
                                    </Button>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    {(recent_sales_orders ?? []).length === 0 ? (
                                        <p className="py-6 text-center text-sm text-muted-foreground">
                                            No hay pedidos registrados.
                                        </p>
                                    ) : (
                                        (recent_sales_orders ?? []).map((order) => (
                                            <Link
                                                key={order.id}
                                                href={salesOrderShow(order.id).url}
                                                className="flex items-center justify-between rounded-lg border border-border p-4 transition hover:bg-muted/40"
                                            >
                                                <div className="space-y-1">
                                                    <p className="font-mono font-semibold">
                                                        Pedido #{order.id}
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {order.client_name}
                                                        {order.required_date
                                                            ? ` · Entrega: ${formatBackendDate(order.required_date)}`
                                                            : ''}
                                                    </p>
                                                </div>
                                                <span
                                                    className={`rounded-full px-2.5 py-1 text-xs font-medium ${salesOrderStatusClass(order.status)}`}
                                                >
                                                    {order.status_label}
                                                </span>
                                            </Link>
                                        ))
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                        <QuickAccessGrid
                            items={[
                                { label: 'Lista de Precios', href: pricesIndex().url, icon: WalletCards },
                                { label: 'Clientes', href: clientsIndex().url, icon: Users },
                                { label: 'Cotizaciones', href: quotationsIndex().url, icon: FileText },
                                { label: 'Pedidos', href: salesOrdersIndex().url, icon: ShoppingCart },
                                { label: 'Productos', href: productsIndex().url, icon: ShoppingBag },
                            ]}
                        />
                    </div>
                </>
            )}
        </div>
    );
}
