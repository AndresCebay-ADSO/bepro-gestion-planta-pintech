import { Head, router } from '@inertiajs/react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import {
    CheckCircle2,
    Clock,
    FlaskConical,
    PackageX,
    TrendingUp,
} from 'lucide-react';
import type { ComponentProps } from 'react';

import { DataListing } from '@/components/data-listing';
import type { DataListingColumn } from '@/components/data-listing';
import { DataTableFilters } from '@/components/data-table-filters';
import { PageHeader } from '@/components/page-header';
import Pagination from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { useFilters } from '@/hooks/use-filters';
import { pillClasses, toneClasses } from '@/lib/tones';
import { index as alertsIndex, resolve as alertResolve } from '@/routes/alerts';
import { show as rawMaterialShow } from '@/routes/raw-materials';
import type { Paginated } from '@/types/ui';

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
    alerts: Paginated<AlertRow>;
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
    can: {
        viewRawMaterials: boolean;
    };
};

function renderTypeIcon(type: string) {
    switch (type) {
        case 'stock_bajo':
            return <PackageX className="size-3.5" aria-hidden="true" />;
        case 'vencimiento_proximo':
            return <Clock className="size-3.5" aria-hidden="true" />;
        case 'variacion_precio':
            return <TrendingUp className="size-3.5" aria-hidden="true" />;
        default:
            return <FlaskConical className="size-3.5" aria-hidden="true" />;
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
    can,
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

    const handleResolve = (alertId: number) => {
        router.patch(alertResolve({ alert: alertId }).url, undefined, {
            preserveScroll: true,
        });
    };

    const columns: DataListingColumn<AlertRow>[] = [
        {
            key: 'type',
            header: 'Tipo',
            cell: (alert) => (
                <span className={`${pillClasses} ${toneClasses.neutral}`}>
                    {renderTypeIcon(alert.type)}
                    {alert.type_label}
                </span>
            ),
            mobile: 'subtitle',
            className: 'whitespace-nowrap',
        },
        {
            key: 'message',
            header: 'Mensaje',
            cell: (alert) => alert.message,
            mobile: 'title',
            // Es lo principal de la fila: se lee entero, salta de línea.
            className: 'min-w-64 whitespace-normal',
        },
        {
            key: 'severity',
            header: 'Urgencia',
            cell: (alert) => (
                <StatusBadge enum="AlertSeverity" value={alert.severity} />
            ),
            mobile: 'status',
        },
        {
            key: 'created_at',
            header: 'Fecha',
            cell: (alert) =>
                alert.created_at
                    ? format(new Date(alert.created_at), 'dd MMM yyyy HH:mm', {
                          locale: es,
                      })
                    : null,
            mobile: 'meta',
            className: 'whitespace-nowrap text-muted-foreground',
        },
        {
            key: 'resolved_by',
            header: 'Resuelta por',
            cell: (alert) =>
                alert.is_resolved
                    ? (alert.resolved_by?.name ?? 'Resuelta')
                    : null,
            mobile: 'meta',
            className: 'text-muted-foreground',
        },
    ];

    return (
        <>
            <Head title="Alertas" />

            <div className="space-y-6 p-6">
                <PageHeader
                    title="Alertas"
                    description="Stock bajo, vencimientos y variaciones de precio en materias primas."
                    actions={
                        <div className="rounded-md border border-border bg-card px-4 py-2 text-sm">
                            <span className="text-muted-foreground">
                                Activas:
                            </span>{' '}
                            <span className="font-semibold text-foreground">
                                {stats.unresolved_count}
                            </span>
                        </div>
                    }
                />

                <DataTableFilters
                    fields={filterFields}
                    filters={filterState}
                    defaultFilters={DEFAULT_FILTERS}
                    onFilter={setFilter}
                    onFilterImmediate={setFilterImmediate}
                    onClear={clearFilters}
                />

                <DataListing
                    rows={alerts.data}
                    rowKey={(alert) => alert.id}
                    // El mensaje abre la materia prima de la alerta (las alertas no tienen detalle propio), si se
                    // tiene permiso de verla: las alertas de precio se ven con el de costos.
                    rowHref={(alert) =>
                        alert.raw_material && can.viewRawMaterials
                            ? rawMaterialShow.url(alert.raw_material.id)
                            : undefined
                    }
                    rowLabel={(alert) => alert.message}
                    columns={columns}
                    actions={(alert) => [
                        {
                            label: 'Resolver',
                            icon: CheckCircle2,
                            onSelect: () => handleResolve(alert.id),
                            hidden: !alert.can.resolve,
                        },
                    ]}
                    empty="No hay alertas para los filtros seleccionados."
                />

                <Pagination paginator={alerts} />
            </div>
        </>
    );
}
