import { Link } from '@inertiajs/react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { BellRing } from 'lucide-react';

import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { AlertBreakdown, RecentAlert } from './types';

interface RecentAlertsCardProps {
    alerts: RecentAlert[];
    alert_breakdown: AlertBreakdown;
    panelHref?: string;
}

export function RecentAlertsCard({
    alerts,
    alert_breakdown,
    panelHref,
}: RecentAlertsCardProps) {
    return (
        <Card className="border-none shadow-lg">
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle className="flex items-center gap-2">
                    <BellRing className="h-5 w-5 text-warning-text" />
                    Alertas activas
                </CardTitle>
                {panelHref && (
                    <Button size="sm" variant="secondary" asChild>
                        <Link href={panelHref}>Ver panel</Link>
                    </Button>
                )}
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="grid grid-cols-3 gap-2 text-center text-xs">
                    <div className="rounded-lg bg-muted/50 p-2">
                        <p className="text-2xl font-semibold">
                            {alert_breakdown.stock_bajo}
                        </p>
                        <p className="text-muted-foreground">Stock</p>
                    </div>
                    <div className="rounded-lg bg-muted/50 p-2">
                        <p className="text-2xl font-semibold">
                            {alert_breakdown.vencimiento_proximo}
                        </p>
                        <p className="text-muted-foreground">Vence</p>
                    </div>
                    <div className="rounded-lg bg-muted/50 p-2">
                        <p className="text-2xl font-semibold">
                            {alert_breakdown.variacion_precio}
                        </p>
                        <p className="text-muted-foreground">Precio</p>
                    </div>
                </div>

                <div className="space-y-2">
                    {alerts.length === 0 ? (
                        <p className="rounded-lg border border-border bg-muted/40 p-4 text-sm text-muted-foreground">
                            No hay alertas activas en este momento.
                        </p>
                    ) : (
                        alerts.map((alert) => (
                            <div
                                key={alert.id}
                                className="rounded-lg border border-border p-3 text-sm"
                            >
                                <div className="mb-1 flex items-center gap-2">
                                    <StatusBadge
                                        enum="AlertSeverity"
                                        value={alert.severity}
                                    />
                                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        {alert.type_label}
                                    </span>
                                </div>
                                <p className="leading-snug text-foreground">
                                    {alert.message}
                                </p>
                                {alert.created_at && (
                                    <p className="mt-2 text-xs text-muted-foreground">
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
