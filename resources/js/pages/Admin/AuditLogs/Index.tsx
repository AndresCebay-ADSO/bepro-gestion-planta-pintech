import { Head } from '@inertiajs/react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import type { ComponentProps } from 'react';
import AuditLogController from '@/actions/App/Http/Controllers/Admin/AuditLogController';

import { DataTableFilters } from '@/components/data-table-filters';
import { PageHeader } from '@/components/page-header';
import Pagination from '@/components/ui/pagination';
import { useFilters } from '@/hooks/use-filters';
import { pillClasses, toneClasses } from '@/lib/tones';
import type { Tone } from '@/types/enums';
import type { PaginationLink } from '@/types/ui';

/**
 * Excepción documentada (DESIGN.md §4): los eventos de auditoría (`created`, `updated`, etc.)
 * provienen de spatie/laravel-activitylog y no son un enum propio de backend. Se mapean aquí
 * a tokens semánticos de Tone usando `lib/tones.ts`.
 */
const eventTones: Record<string, Tone> = {
    created: 'success',
    updated: 'info',
    deleted: 'danger',
    failed_login: 'warning',
    role_changed: 'info',
};

type Causer = {
    id: number;
    name: string;
    email: string;
};

type ActivityLog = {
    id: number;
    logName: string;
    description: string;
    event: string;
    subjectType: string;
    subjectId: number;
    causerType: string | null;
    causerId: number | null;
    causer: Causer | null;
    properties: Record<string, any>;
    createdAt: string;
};

type Props = {
    logs: {
        data: ActivityLog[];
        links: PaginationLink[];
    };
    filters: {
        search?: string;
        log_name?: string;
        event?: string;
        date_from?: string;
        date_to?: string;
    };
    options: {
        logNames: string[];
        events: string[];
    };
};

export default function AuditLogsIndex({ logs, filters, options }: Props) {
    const {
        filters: filterState,
        setFilter,
        setFilterImmediate,
        clearFilters,
    } = useFilters({
        routeUrl: AuditLogController.index.url(),
        initialFilters: {
            search: filters.search ?? '',
            log_name: filters.log_name ?? '',
            event: filters.event ?? '',
            date_from: filters.date_from ?? '',
            date_to: filters.date_to ?? '',
        },
    });

    const getEventLabel = (event: string) => {
        switch (event) {
            case 'created':
                return 'Creado';
            case 'updated':
                return 'Actualizado';
            case 'deleted':
                return 'Eliminado';
            case 'failed_login':
                return 'Inicio de sesión fallido';
            case 'role_changed':
                return 'Rol cambiado';
            default:
                return event || 'default';
        }
    };

    const formatProperties = (properties: Record<string, any>) => {
        if (!properties || Object.keys(properties).length === 0) {
            return '-';
        }

        const attributes = properties.attributes;
        const old = properties.old;

        if (!attributes && !old) {
            // Not standard Spatie format, fallback to JSON
            const jsonStr = JSON.stringify(properties);

            if (jsonStr.length > 50) {
                return (
                    <div className="group relative cursor-help">
                        <span className="inline-block max-w-[150px] truncate text-muted-foreground">
                            {jsonStr}
                        </span>
                        <div className="absolute bottom-full left-0 z-10 mb-1 hidden max-w-sm rounded-md border bg-popover p-2 text-xs whitespace-pre-wrap text-popover-foreground shadow-md group-hover:block">
                            {JSON.stringify(properties, null, 2)}
                        </div>
                    </div>
                );
            }

            return <span className="text-muted-foreground">{jsonStr}</span>;
        }

        const keys = attributes
            ? Object.keys(attributes)
            : Object.keys(old || {});

        if (keys.length === 0) {
            return '-';
        }

        const formatVal = (val: any) => {
            if (val === null) {
                return (
                    <span className="text-muted-foreground italic">null</span>
                );
            }

            if (typeof val === 'boolean') {
                return val ? 'Sí' : 'No';
            }

            if (typeof val === 'object') {
                const jsonStr = JSON.stringify(val);

                if (jsonStr.length > 30) {
                    return (
                        <span
                            className="cursor-help font-mono text-xs text-muted-foreground"
                            title={jsonStr}
                        >
                            [Objeto]
                        </span>
                    );
                }

                return (
                    <span className="font-mono text-xs text-muted-foreground">
                        {jsonStr}
                    </span>
                );
            }

            return String(val);
        };

        const renderChangeList = () => (
            <ul className="space-y-1.5 text-xs">
                {keys.map((key) => {
                    const newVal = attributes ? attributes[key] : undefined;
                    const oldVal = old ? old[key] : undefined;
                    const hasOld = old && key in old;
                    const hasNew = attributes && key in attributes;

                    if (hasOld && hasNew && newVal === oldVal) {
                        return null;
                    }

                    return (
                        <li
                            key={key}
                            className="flex flex-col gap-0.5 md:flex-row md:items-start md:gap-1.5"
                        >
                            <span className="shrink-0 font-semibold text-foreground/80 md:w-1/3">
                                {key}:
                            </span>
                            <div className="flex flex-wrap items-center gap-1.5 md:w-2/3">
                                {hasOld && hasNew ? (
                                    <>
                                        <span className="break-all text-destructive/80 line-through">
                                            {formatVal(oldVal)}
                                        </span>
                                        <span className="text-muted-foreground">
                                            ➔
                                        </span>
                                        <span className="font-medium break-all text-success-text">
                                            {formatVal(newVal)}
                                        </span>
                                    </>
                                ) : hasNew ? (
                                    <span className="break-all text-muted-foreground">
                                        {formatVal(newVal)}
                                    </span>
                                ) : (
                                    <span className="break-all text-destructive/80 line-through">
                                        {formatVal(oldVal)}
                                    </span>
                                )}
                            </div>
                        </li>
                    );
                })}
            </ul>
        );

        if (keys.length > 2) {
            return (
                <div className="group relative cursor-help">
                    <span className="inline-flex items-center rounded-md border border-border bg-muted/40 px-2 py-1 text-xs font-medium text-muted-foreground transition-colors group-hover:bg-muted">
                        {keys.length} atributos modificados
                    </span>
                    <div className="absolute bottom-full left-0 z-10 mb-2 hidden w-[300px] rounded-lg border bg-popover p-3 shadow-md group-hover:block sm:w-[400px]">
                        {renderChangeList()}
                    </div>
                </div>
            );
        }

        return renderChangeList();
    };

    const filterFields: ComponentProps<typeof DataTableFilters>['fields'] = [
        {
            type: 'text',
            name: 'search',
            label: 'Buscar',
            placeholder: 'Usuario, descripción...',
        },
        {
            type: 'select',
            name: 'log_name',
            label: 'Módulo',
            options: options.logNames.map((name) => ({
                value: name,
                label: name,
            })),
        },
        {
            type: 'select',
            name: 'event',
            label: 'Evento',
            options: options.events.map((ev) => ({
                value: ev,
                label: getEventLabel(ev),
            })),
        },
        {
            type: 'date-range',
            nameFrom: 'date_from',
            nameTo: 'date_to',
            label: 'Fecha',
        },
    ];

    return (
        <>
            <Head title="Auditoría" />

            <div className="space-y-6 p-6">
                <PageHeader
                    title="Auditoría"
                    description="Auditoría de eventos, cambios de sistema y accesos."
                />

                {/* Filters */}
                <DataTableFilters
                    fields={filterFields}
                    filters={filterState}
                    onFilter={setFilter}
                    onFilterImmediate={setFilterImmediate}
                    onClear={clearFilters}
                />

                {/* Tabla */}
                <div className="overflow-x-auto rounded-lg border border-border bg-card">
                    <table className="w-full text-sm">
                        <thead className="border-b border-border bg-muted/40">
                            <tr>
                                <th className="p-3 text-left font-medium">
                                    Fecha
                                </th>
                                <th className="p-3 text-left font-medium">
                                    Causante
                                </th>
                                <th className="p-3 text-left font-medium">
                                    Módulo
                                </th>
                                <th className="p-3 text-center font-medium">
                                    Evento
                                </th>
                                <th className="p-3 text-left font-medium">
                                    Descripción
                                </th>
                                <th className="p-3 text-left font-medium">
                                    Detalles (Properties)
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            {logs.data.map((log) => (
                                <tr
                                    key={log.id}
                                    className="border-b border-border/60 transition last:border-0 hover:bg-muted/30"
                                >
                                    <td className="p-3 text-xs whitespace-nowrap text-muted-foreground">
                                        {format(
                                            new Date(log.createdAt),
                                            'dd MMM yyyy, HH:mm',
                                            { locale: es },
                                        )}
                                    </td>
                                    <td className="p-3">
                                        {log.causer ? (
                                            <div>
                                                <div className="font-medium">
                                                    {log.causer.name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {log.causer.email}
                                                </div>
                                            </div>
                                        ) : (
                                            <span className="text-muted-foreground italic">
                                                Sistema / Anónimo
                                            </span>
                                        )}
                                    </td>
                                    <td className="p-3">
                                        <span className="rounded bg-secondary px-2 py-1 text-xs tracking-wider text-secondary-foreground uppercase">
                                            {log.logName}
                                        </span>
                                    </td>
                                    <td className="p-3 text-center">
                                        <span
                                            className={`${pillClasses} ${toneClasses[eventTones[log.event] ?? 'neutral']}`}
                                        >
                                            {getEventLabel(log.event)}
                                        </span>
                                    </td>
                                    <td className="max-w-[200px] p-3 break-words text-foreground">
                                        {log.description}
                                    </td>
                                    <td className="max-w-[200px] p-3 font-mono text-xs text-muted-foreground">
                                        {formatProperties(log.properties)}
                                    </td>
                                </tr>
                            ))}

                            {logs.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="p-10 text-center text-sm text-muted-foreground"
                                    >
                                        No hay registros que coincidan con los
                                        filtros.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Paginación */}
                <div className="mt-4 flex justify-center">
                    <Pagination links={logs.links} />
                </div>
            </div>
        </>
    );
}
