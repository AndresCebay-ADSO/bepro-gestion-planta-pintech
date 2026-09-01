import { Head, Link, router, usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import type { ComponentProps } from 'react';

import { DataTableFilters } from '@/components/data-table-filters';
import { Button } from '@/components/ui/button';
import Pagination from '@/components/ui/pagination';
import { useFilters } from '@/hooks/use-filters';
import { index as alertsIndex, resolve as alertResolve } from '@/routes/alerts';
import { show as rawMaterialShow } from '@/routes/raw-materials';
import type { PaginationLink } from '@/types/ui';

type AlertRow = {
    id: number;
    type: string;
    type_label: string;
    severity: string;
    severity_label: string;
    message: string;
    is_resolved: boolean;
    created_at: string | null;
    resolved_at: string | null;
    raw_material: { id: number; code: string } | null;
    batch: {
        id: number;
        lot_number: string | null;
        expiry_date: string | null;
    } | null;
    resolved_by: { id: number; name: string } | null;
    can: {
        resolve: boolean;
    };
};

type Option = {
    value: string;
    label: string;
};

type Props = {
    alerts: {
        data: AlertRow[];
        links: PaginationLink[];
    };
    filters: {
        status?: string;
        type?: string;
        severity?: string;
    };
    statusOptions: Option[];
    typeOptions: Option[];
    severityOptions: Option[];
    stats: {
        unresolved_count: number;
    };
};

function severityClass(severity: string): string {
    switch (severity) {
        case 'alta':
            return 'bg-red-500/15 text-red-700 dark:text-red-300';
        case 'media':
            return 'bg-amber-500/15 text-amber-700 dark:text-amber-300';
        default:
            return 'bg-slate-500/15 text-slate-700 dark:text-slate-300';
    }
}

function typeClass(type: string): string {
    switch (type) {
        case 'stock_bajo':
            return 'bg-orange-500/15 text-orange-700 dark:text-orange-300';
        case 'vencimiento_proximo':
            return 'bg-purple-500/15 text-purple-700 dark:text-purple-300';
        case 'variacion_precio':
            return 'bg-blue-500/15 text-blue-700 dark:text-blue-300';
        default:
            return 'bg-slate-500/15 text-slate-700 dark:text-slate-300';
    }
}

const DEFAULT_FILTERS = {
    status: 'active',
    type: '',
    severity: '',
};

export default function AlertsIndex({
    alerts,
    filters,
    statusOptions,
    typeOptions,
    severityOptions,
    stats,
}: Props) {
    const {
        filters: filterState,
        setFilter,
        setFilterImmediate,
        clearFilters,
    } = useFilters({
        routeUrl: alertsIndex().url,
        initialFilters: {
            status: filters.status ?? DEFAULT_FILTERS.status,
            type: filters.type ?? DEFAULT_FILTERS.type,
            severity: filters.severity ?? DEFAULT_FILTERS.severity,
        },
        defaultFilters: DEFAULT_FILTERS,
    });

    const filterFields: ComponentProps<typeof DataTableFilters>['fields'] = [
        {
            type: 'select',
            name: 'status',
            label: 'Estado',
            options: statusOptions,
            allValue: 'all',
        },
        {
            type: 'select',
            name: 'type',
            label: 'Tipo de alerta',
            options: typeOptions,
        },
        {
            type: 'select',
            name: 'severity',
            label: 'Urgencia',
            options: severityOptions,
        },
    ];

    const flash = usePage<{
        flash?: { success?: string; error?: string };
    }>().props.flash;

    const handleResolve = (alertId: number) => {
        router.patch(alertResolve({ alert: alertId }).url, undefined, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Alertas" />

            <div className="space-y-6 p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
                            Alertas del sistema
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Stock bajo, vencimientos y variaciones de precio en
                            materias primas.
                        </p>
                    </div>
                    <div className="rounded-md border border-border bg-card px-4 py-2 text-sm">
                        <span className="text-muted-foreground">Activas:</span>{' '}
                        <span className="font-semibold text-foreground">
                            {stats.unresolved_count}
                        </span>
                    </div>
                </div>

                {flash?.success && (
                    <div className="rounded-md border border-emerald-500/25 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-700 dark:text-emerald-300">
                        {flash.success}
                    </div>
                )}

                <DataTableFilters
                    fields={filterFields}
                    filters={filterState}
                    defaultFilters={DEFAULT_FILTERS}
                    onFilter={setFilter}
                    onFilterImmediate={setFilterImmediate}
                    onClear={clearFilters}
                />

                <div className="overflow-hidden rounded-lg border border-border bg-card">
                    <table className="min-w-full divide-y divide-border text-sm">
                        <thead className="bg-muted/40">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    Tipo
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Mensaje
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Urgencia
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Fecha
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {alerts.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No hay alertas para los filtros
                                        seleccionados.
                                    </td>
                                </tr>
                            ) : (
                                alerts.data.map((alert) => (
                                    <tr key={alert.id}>
                                        <td className="px-4 py-3 align-top">
                                            <span
                                                className={`inline-flex rounded-md px-2 py-1 text-xs font-medium ${typeClass(alert.type)}`}
                                            >
                                                {alert.type_label}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 align-top">
                                            <p className="font-medium text-foreground">
                                                {alert.message}
                                            </p>
                                            {alert.raw_material && (
                                                <Link
                                                    href={rawMaterialShow.url(
                                                        alert.raw_material.id,
                                                    )}
                                                    className="mt-1 inline-block font-mono text-xs text-primary hover:underline"
                                                >
                                                    {alert.raw_material.code}
                                                </Link>
                                            )}
                                            {alert.is_resolved &&
                                                alert.resolved_by && (
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        Resuelta por{' '}
                                                        {alert.resolved_by.name}
                                                    </p>
                                                )}
                                        </td>
                                        <td className="px-4 py-3 align-top">
                                            <span
                                                className={`inline-flex rounded-md px-2 py-1 text-xs font-medium ${severityClass(alert.severity)}`}
                                            >
                                                {alert.severity_label}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 align-top text-muted-foreground">
                                            {alert.created_at
                                                ? format(
                                                      new Date(
                                                          alert.created_at,
                                                      ),
                                                      'dd MMM yyyy HH:mm',
                                                      { locale: es },
                                                  )
                                                : '-'}
                                        </td>
                                        <td className="px-4 py-3 text-right align-top">
                                            {alert.can.resolve ? (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        handleResolve(alert.id)
                                                    }
                                                >
                                                    Resolver
                                                </Button>
                                            ) : (
                                                <span className="text-xs text-muted-foreground">
                                                    {alert.is_resolved
                                                        ? 'Resuelta'
                                                        : '-'}
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination links={alerts.links} />
            </div>
        </>
    );
}
