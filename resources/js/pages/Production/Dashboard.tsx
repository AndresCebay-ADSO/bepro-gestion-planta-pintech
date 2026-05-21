import { Head, Link } from '@inertiajs/react';
import {
    Factory,
    ClipboardList,
    CheckCircle2,
    Timer,
    ArrowRight,
    Unlock,
    Lock,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index as productionOrdersIndex } from '@/routes/production-orders';

interface ProductionDashboardProps {
    role: string;
    userName: string;
    stats: {
        pendingOrders: number;
        activeOrders: number;
        completedToday: number;
    };
}

export default function ProductionDashboard({
    role,
    userName,
    stats,
}: ProductionDashboardProps) {
    return (
        <div className="min-h-screen bg-slate-50/50 dark:bg-slate-950">
            <Head title="Panel de Producción" />

            {/* Header Hero */}
            <div className="relative overflow-hidden bg-slate-900 px-8 py-16 text-white">
                <div className="absolute inset-0 bg-linear-to-br from-orange-600/20 to-transparent" />
                <div className="relative mx-auto max-w-6xl">
                    <div className="mb-6 flex items-center gap-4">
                        <div className="rounded-xl bg-orange-600 p-3 shadow-lg shadow-orange-600/20">
                            <Factory className="h-8 w-8" />
                        </div>
                        <div>
                            <h1 className="text-4xl font-bold tracking-tight">
                                Centro de Producción
                            </h1>
                            <p className="text-slate-400">
                                Gestión de planta y control de procesos en
                                tiempo real.
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-4">
                        <div className="flex items-center gap-2 rounded-lg bg-white/5 px-4 py-2 backdrop-blur-sm">
                            <span className="text-slate-400">Usuario:</span>
                            <span className="font-semibold">{userName}</span>
                        </div>
                        <div className="flex items-center gap-2 rounded-lg bg-white/5 px-4 py-2 backdrop-blur-sm">
                            <span className="text-slate-400">Rol:</span>
                            <Badge className="border-orange-600/30 bg-orange-600/20 text-orange-400">
                                {role.toUpperCase()}
                            </Badge>
                        </div>
                    </div>
                </div>
            </div>

            {/* Main Content */}
            <div className="mx-auto -mt-8 max-w-6xl px-8 pb-12">
                {/* Stats Grid */}
                <div className="mb-8 grid grid-cols-1 gap-6 md:grid-cols-3">
                    <Card className="border-none shadow-xl shadow-slate-200/50 dark:shadow-none">
                        <CardContent className="p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <div className="rounded-full bg-slate-100 p-2 text-slate-600 dark:bg-slate-900">
                                    <Timer className="h-5 w-5" />
                                </div>
                                <span className="text-xs font-semibold tracking-wider text-slate-400 uppercase">
                                    Pendientes
                                </span>
                            </div>
                            <div className="text-4xl font-bold text-slate-900 dark:text-white">
                                {stats.pendingOrders}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-none shadow-xl shadow-slate-200/50 dark:shadow-none">
                        <CardContent className="p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <div className="rounded-full bg-orange-100 p-2 text-orange-600">
                                    <ClipboardList className="h-5 w-5" />
                                </div>
                                <span className="text-xs font-semibold tracking-wider text-slate-400 uppercase">
                                    En Proceso
                                </span>
                            </div>
                            <div className="text-4xl font-bold text-slate-900 dark:text-white">
                                {stats.activeOrders}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-none shadow-xl shadow-slate-200/50 dark:shadow-none">
                        <CardContent className="p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <div className="rounded-full bg-green-100 p-2 text-green-600">
                                    <CheckCircle2 className="h-5 w-5" />
                                </div>
                                <span className="text-xs font-semibold tracking-wider text-slate-400 uppercase">
                                    Finalizadas Hoy
                                </span>
                            </div>
                            <div className="text-4xl font-bold text-slate-900 dark:text-white">
                                {stats.completedToday}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    {/* Access & Actions */}
                    <div className="space-y-8 lg:col-span-2">
                        <Card className="border-none shadow-lg">
                            <CardHeader>
                                <CardTitle className="text-xl">
                                    Flujos de Trabajo
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <Button
                                    asChild
                                    variant="outline"
                                    className="group h-24 justify-start gap-4 border-slate-200 bg-white transition-all hover:border-orange-500 dark:border-slate-800 dark:bg-slate-900"
                                    size="lg"
                                >
                                    <Link href={productionOrdersIndex().url}>
                                        <div className="rounded-full bg-slate-100 p-3 transition-colors group-hover:bg-orange-50 dark:bg-slate-800 dark:group-hover:bg-orange-950">
                                            <ClipboardList className="h-6 w-6 text-slate-600 group-hover:text-orange-600" />
                                        </div>
                                        <div className="flex translate-y-1 flex-col items-start">
                                            <span className="font-bold">
                                                Órdenes Activas
                                            </span>
                                            <span className="text-xs font-normal text-slate-500">
                                                Ver y gestionar la fabricación
                                            </span>
                                        </div>
                                        <ArrowRight className="ml-auto opacity-0 transition-opacity group-hover:opacity-100" />
                                    </Link>
                                </Button>

                                <Button
                                    asChild
                                    variant="outline"
                                    className="group h-24 justify-start gap-4 border-slate-200 bg-white transition-all hover:border-orange-500 dark:border-slate-800 dark:bg-slate-900"
                                    size="lg"
                                >
                                    <Link href="/production-orders">
                                        <div className="rounded-full bg-slate-100 p-3 transition-colors group-hover:bg-orange-50 dark:bg-slate-800 dark:group-hover:bg-orange-950">
                                            <Factory className="h-6 w-6 text-slate-600 group-hover:text-orange-600" />
                                        </div>
                                        <div className="flex translate-y-1 flex-col items-start">
                                            <span className="font-bold">
                                                Histórico
                                            </span>
                                            <span className="text-xs font-normal text-slate-500">
                                                Consultar lotes pasados
                                            </span>
                                        </div>
                                        <ArrowRight className="ml-auto opacity-0 transition-opacity group-hover:opacity-100" />
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>

                        {/* Recent Activity Placeholder */}
                        <Card className="border-none shadow-lg">
                            <CardHeader>
                                <CardTitle className="text-xl">
                                    Permisos y Accesos
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                {[
                                    { label: 'Crear Órdenes', allowed: true },
                                    {
                                        label: 'Cierre de Inventario',
                                        allowed: true,
                                    },
                                    {
                                        label: 'Gestión de Fórmulas',
                                        allowed: true,
                                    },
                                    {
                                        label: 'Administrar Usuarios',
                                        allowed: false,
                                    },
                                ].map((perm, i) => (
                                    <div
                                        key={i}
                                        className={`flex items-center gap-3 rounded-xl border p-4 ${perm.allowed ? 'border-green-100 bg-green-50/50 text-green-700' : 'border-slate-100 bg-slate-50 text-slate-400'} dark:border-slate-800 dark:bg-slate-900`}
                                    >
                                        {perm.allowed ? (
                                            <Unlock className="h-4 w-4" />
                                        ) : (
                                            <Lock className="h-4 w-4" />
                                        )}
                                        <span className="text-sm font-medium">
                                            {perm.label}
                                        </span>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Sidebar Info */}
                    <div className="space-y-6">
                        <Card className="relative overflow-hidden border-none bg-slate-900 text-white shadow-2xl">
                            <div className="absolute top-0 right-0 p-4 opacity-10">
                                <Factory className="h-24 w-24" />
                            </div>
                            <CardHeader>
                                <CardTitle className="text-lg">
                                    Estado de Planta
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-1">
                                    <p className="text-xs tracking-tighter text-slate-400 uppercase">
                                        Localización Actual
                                    </p>
                                    <p className="flex items-center gap-2 font-bold">
                                        <span className="h-2 w-2 rounded-full bg-green-500" />
                                        Cali - Planta Principal
                                    </p>
                                </div>
                                <Separator className="bg-white/10" />
                                <div className="space-y-1">
                                    <p className="text-xs tracking-tighter text-slate-400 uppercase">
                                        Turno
                                    </p>
                                    <p className="font-bold">Mañana / Tarde</p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </div>
    );
}

const Separator = ({ className }: { className?: string }) => (
    <div className={`h-[1px] w-full ${className}`} />
);
