import { Link } from '@inertiajs/react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { BellRing } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { AlertBreakdown, RecentAlert } from './types';

interface RecentAlertsCardProps {
    alerts: RecentAlert[];
    alert_breakdown: AlertBreakdown;
    panelHref?: string;
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

export function RecentAlertsCard({
    alerts,
    alert_breakdown,
    panelHref,
}: RecentAlertsCardProps) {
    return (
        <Card className="border-none bg-slate-900 text-white shadow-2xl">
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle className="flex items-center gap-2 text-lg">
                    <BellRing className="h-5 w-5 text-orange-400" />
                    Alertas activas
                </CardTitle>
                {panelHref && (
                    <Button
                        size="sm"
                        variant="secondary"
                        asChild
                        className="bg-white/10 text-white hover:bg-white/20"
                    >
                        <Link href={panelHref}>Ver panel</Link>
                    </Button>
                )}
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
                            {alert_breakdown.vencimiento_proximo}
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
                    {alerts.length === 0 ? (
                        <p className="rounded-lg border border-white/10 bg-white/5 p-4 text-sm text-slate-400">
                            No hay alertas activas en este momento.
                        </p>
                    ) : (
                        alerts.map((alert) => (
                            <div
                                key={alert.id}
                                className={`rounded-lg border p-3 text-sm ${alertSeverityClass(alert.severity)}`}
                            >
                                <p className="mb-1 text-xs font-semibold tracking-wide text-slate-300 uppercase">
                                    {alert.type_label} · {alert.severity_label}
                                </p>
                                <p className="leading-snug text-white">
                                    {alert.message}
                                </p>
                                {alert.created_at && (
                                    <p className="mt-2 text-xs text-slate-400">
                                        {format(
                                            new Date(alert.created_at),
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
    );
}
