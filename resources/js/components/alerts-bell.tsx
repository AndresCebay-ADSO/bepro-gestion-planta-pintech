import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { formatRelativeTime } from '@/components/formatted-date';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatBadgeCount, formatNumber } from '@/lib/formatters';
import { index as alertsIndex } from '@/routes/alerts';

/**
 * Campana del encabezado con las alertas sin resolver que ve el usuario: las props compartidas `unresolvedAlertsCount`
 * y `recentAlerts` (las cinco más recientes, ya filtradas por tipo según sus permisos). Sin `alerts.view` no se muestra.
 */
export function AlertsBell() {
    const { auth, unresolvedAlertsCount, recentAlerts } = usePage().props;

    if (!auth.user?.permissions?.includes('alerts.view')) {
        return null;
    }

    const hasUnresolved = unresolvedAlertsCount > 0;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="outline"
                    size="icon"
                    className="relative size-10 text-muted-foreground hover:text-foreground"
                    aria-label={
                        hasUnresolved
                            ? `Alertas: ${formatNumber(unresolvedAlertsCount)} sin resolver`
                            : 'Alertas'
                    }
                >
                    <Bell className="size-4" />
                    {hasUnresolved && (
                        <span
                            aria-hidden="true"
                            className="absolute -top-1.5 -right-1.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-destructive px-1 text-xs font-medium text-destructive-foreground tabular-nums ring-2 ring-background"
                        >
                            {formatBadgeCount(unresolvedAlertsCount)}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80">
                <DropdownMenuLabel>Alertas pendientes</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {recentAlerts.length === 0 ? (
                    <p className="px-2 py-4 text-center text-sm text-muted-foreground">
                        No hay alertas pendientes
                    </p>
                ) : (
                    recentAlerts.map((alert) => {
                        const createdAgo = formatRelativeTime(alert.created_at);

                        return (
                            <DropdownMenuItem key={alert.id} asChild>
                                <Link
                                    href={alertsIndex({
                                        query: { type: alert.type },
                                    })}
                                    className="flex cursor-pointer flex-col items-start gap-1"
                                >
                                    <span className="flex items-center gap-2">
                                        <StatusBadge
                                            enum="AlertSeverity"
                                            value={alert.severity}
                                        />
                                        {createdAgo && (
                                            <span className="text-xs text-muted-foreground">
                                                {createdAgo}
                                            </span>
                                        )}
                                    </span>
                                    <span className="text-sm text-foreground">
                                        {alert.message}
                                    </span>
                                </Link>
                            </DropdownMenuItem>
                        );
                    })
                )}
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link
                        href={alertsIndex()}
                        className="cursor-pointer justify-center font-medium"
                    >
                        Ver todas
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
