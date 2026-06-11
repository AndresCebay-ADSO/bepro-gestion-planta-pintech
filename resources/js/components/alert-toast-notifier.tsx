import { Link, usePage } from '@inertiajs/react';
import { BellRing, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { index as alertsIndex } from '@/routes/alerts';
import { cn } from '@/lib/utils';

type AlertToast = {
    id: number;
    message: string;
    severity: string;
    type: string;
    type_label: string;
};

const STORAGE_KEY = 'pintech_seen_alert_ids';

function severityAccent(severity: string): string {
    switch (severity) {
        case 'alta':
            return 'border-l-red-500 dark:border-l-red-500';
        case 'media':
            return 'border-l-amber-500 dark:border-l-amber-500';
        default:
            return 'border-l-slate-500 dark:border-l-slate-400';
    }
}

function readSeenAlertIds(): Set<number> {
    try {
        const raw = sessionStorage.getItem(STORAGE_KEY);
        if (!raw) {
            return new Set();
        }

        const parsed = JSON.parse(raw) as number[];

        return new Set(parsed);
    } catch {
        return new Set();
    }
}

function writeSeenAlertIds(ids: Set<number>): void {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(Array.from(ids)));
}

export function AlertToastNotifier() {
    const { auth, recentAlerts = [], flash } = usePage<{
        auth: { user: { role_names?: string[] } | null };
        recentAlerts?: AlertToast[];
        flash?: {
            new_alerts?: AlertToast[];
        };
    }>().props;

    const [toasts, setToasts] = useState<AlertToast[]>([]);

    const canReceiveAlerts =
        auth.user?.role_names?.some((role) =>
            ['admin', 'produccion'].includes(role),
        ) ?? false;

    useEffect(() => {
        if (!canReceiveAlerts) {
            return;
        }

        const seenIds = readSeenAlertIds();
        const flashAlerts = flash?.new_alerts ?? [];

        if (seenIds.size === 0 && recentAlerts.length > 0) {
            recentAlerts.forEach((alert) => seenIds.add(alert.id));
            writeSeenAlertIds(seenIds);

            if (flashAlerts.length > 0) {
                setToasts(flashAlerts);
            }

            return;
        }

        const incoming = [
            ...flashAlerts,
            ...recentAlerts.filter((alert) => !seenIds.has(alert.id)),
        ];

        const uniqueIncoming = incoming.filter(
            (alert, index, list) =>
                list.findIndex((item) => item.id === alert.id) === index,
        );

        if (uniqueIncoming.length === 0) {
            return;
        }

        setToasts((current) => {
            const existingIds = new Set(current.map((toast) => toast.id));
            const next = uniqueIncoming.filter(
                (alert) => !existingIds.has(alert.id),
            );

            return [...current, ...next];
        });

        uniqueIncoming.forEach((alert) => seenIds.add(alert.id));
        writeSeenAlertIds(seenIds);
    }, [canReceiveAlerts, flash?.new_alerts, recentAlerts]);

    useEffect(() => {
        if (toasts.length === 0) {
            return;
        }

        const timers = toasts.map((toast) =>
            window.setTimeout(() => {
                setToasts((current) =>
                    current.filter((item) => item.id !== toast.id),
                );
            }, 8000),
        );

        return () => {
            timers.forEach((timer) => window.clearTimeout(timer));
        };
    }, [toasts]);

    if (!canReceiveAlerts || toasts.length === 0) {
        return null;
    }

    return (
        <div className="pointer-events-none fixed right-4 bottom-4 z-50 flex w-full max-w-sm flex-col gap-3">
            {toasts.map((toast) => (
                <div
                    key={toast.id}
                    className={cn(
                        'pointer-events-auto overflow-hidden rounded-lg border border-border border-l-4 bg-card text-foreground shadow-lg',
                        severityAccent(toast.severity),
                    )}
                >
                    <div className="flex items-start gap-3 p-4">
                        <BellRing className="mt-0.5 h-5 w-5 shrink-0" />
                        <div className="min-w-0 flex-1 space-y-1">
                            <p className="text-xs font-semibold tracking-wide uppercase opacity-80">
                                Nueva alerta · {toast.type_label}
                            </p>
                            <p className="text-sm leading-snug">
                                {toast.message}
                            </p>
                            <Link
                                href={alertsIndex().url}
                                className="inline-block text-xs font-medium underline underline-offset-2 opacity-90 hover:opacity-100"
                            >
                                Ver alertas
                            </Link>
                        </div>
                        <button
                            type="button"
                            onClick={() =>
                                setToasts((current) =>
                                    current.filter(
                                        (item) => item.id !== toast.id,
                                    ),
                                )
                            }
                            className="rounded-md p-1 opacity-70 transition hover:opacity-100"
                            aria-label="Cerrar notificación"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                </div>
            ))}
        </div>
    );
}
