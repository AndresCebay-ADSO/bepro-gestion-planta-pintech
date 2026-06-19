import { Head, Link } from '@inertiajs/react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import {
    CheckCircle2,
    ClipboardList,
    Factory,
    LayoutGrid,
    Send,
    Timer,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index as productionOrdersIndex } from '@/routes/production-orders';
import { show as productionOrderShow } from '@/routes/production-orders';

type DashboardStats = {
    pending_orders: number;
    active_orders: number;
    submitted_orders: number;
    completed_today: number;
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

interface OperatorDashboardProps {
    role: string;
    userName: string;
    stats: DashboardStats;
    recent_orders: RecentOrder[];
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

export default function OperatorDashboard({
    role,
    userName,
    stats,
    recent_orders,
}: OperatorDashboardProps) {
    return (
        <div className="min-h-screen bg-slate-50/50 dark:bg-slate-950">
            <Head title="Panel de Operador" />

            <div className="relative overflow-hidden bg-slate-900 px-8 py-12 text-white">
                <div className="absolute inset-0 bg-linear-to-br from-blue-600/20 to-transparent" />
                <div className="relative mx-auto max-w-6xl">
                    <div className="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
                        <div className="flex items-start gap-4">
                            <div className="rounded-xl bg-blue-600 p-3 shadow-lg shadow-blue-600/20">
                                <Factory className="h-8 w-8" />
                            </div>
                            <div>
                                <h1 className="text-3xl font-bold tracking-tight md:text-4xl">
                                    Panel de Planta
                                </h1>
                                <p className="mt-1 text-slate-400">
                                    Hola, {userName}. Órdenes asignadas para
                                    ejecución y precierre.
                                </p>
                            </div>
                        </div>
                        <Badge className="w-fit border-blue-600/30 bg-blue-600/20 text-blue-300">
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
                                    Pendientes
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
                                    En proceso
                                </p>
                                <p className="text-3xl font-bold">
                                    {stats.active_orders}
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
                                    En revisión
                                </p>
                                <p className="text-3xl font-bold">
                                    {stats.submitted_orders}
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
                </div>

                <Card className="border-none shadow-lg">
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle className="flex items-center gap-2 text-lg">
                            <LayoutGrid className="h-5 w-5 text-primary" />
                            Órdenes activas
                        </CardTitle>
                        <Button asChild variant="outline" size="sm">
                            <Link href={productionOrdersIndex().url}>
                                Ver todas
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {recent_orders.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No hay órdenes pendientes de trabajo en planta.
                            </p>
                        ) : (
                            recent_orders.map((order) => (
                                <Link
                                    key={order.id}
                                    href={productionOrderShow(order.id).url}
                                    className="flex items-center justify-between rounded-lg border border-border/60 p-4 transition-colors hover:bg-muted/40"
                                >
                                    <div>
                                        <p className="font-mono font-semibold">
                                            {order.order_number}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {order.product_code ?? 'Sin producto'}{' '}
                                            • Plan:{' '}
                                            {order.planned_date
                                                ? format(
                                                      new Date(
                                                          `${order.planned_date}T12:00:00`,
                                                      ),
                                                      'dd MMM yyyy',
                                                      { locale: es },
                                                  )
                                                : '—'}
                                        </p>
                                    </div>
                                    <Badge
                                        className={orderStatusClass(order.status)}
                                    >
                                        {order.status_label}
                                    </Badge>
                                </Link>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
