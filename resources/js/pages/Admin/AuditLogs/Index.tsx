import { Head, useForm, router } from '@inertiajs/react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import type { FormEvent } from 'react';
import AuditLogController from '@/actions/App/Http/Controllers/Admin/AuditLogController';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Pagination from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { PaginationLink } from '@/types/ui';

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
    const { data, setData, get } = useForm({
        search: filters.search ?? '',
        log_name: filters.log_name ?? 'all',
        event: filters.event ?? 'all',
        date_from: filters.date_from ?? '',
        date_to: filters.date_to ?? '',
    });

    const handleFilter = (e?: FormEvent) => {
        if (e) {
            e.preventDefault();
        }

        get(AuditLogController.index.url(), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const clearFilters = () => {
        router.get(AuditLogController.index.url());
    };

    const getEventBadge = (event: string) => {
        switch (event) {
            case 'created':
                return 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-300';
            case 'updated':
                return 'bg-blue-500/15 text-blue-600 dark:text-blue-300';
            case 'deleted':
                return 'bg-red-500/15 text-red-600 dark:text-red-300';
            case 'failed_login':
                return 'bg-amber-500/15 text-amber-600 dark:text-amber-300';
            case 'role_changed':
                return 'bg-purple-500/15 text-purple-600 dark:text-purple-300';
            default:
                return 'bg-slate-500/15 text-slate-600 dark:text-slate-300';
        }
    };

    const formatProperties = (properties: Record<string, any>) => {
        if (!properties || Object.keys(properties).length === 0) {
            return '-';
        }

        // Truncating to avoid breaking UI
        const jsonStr = JSON.stringify(properties);

        if (jsonStr.length > 50) {
            return (
                <div className="group relative cursor-help">
                    <span className="inline-block max-w-[150px] truncate">
                        {jsonStr}
                    </span>
                    <div className="absolute bottom-full left-0 z-10 mb-1 hidden max-w-sm rounded-md border bg-popover p-2 text-xs whitespace-pre-wrap text-popover-foreground shadow-md group-hover:block">
                        {JSON.stringify(properties, null, 2)}
                    </div>
                </div>
            );
        }

        return jsonStr;
    };

    return (
        <>
            <Head title="Registro de Actividades" />

            <div className="space-y-6 p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
                            Registro de Actividades
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Auditoría de eventos, cambios de sistema y accesos.
                        </p>
                    </div>
                </div>

                {/* Filters */}
                <div className="rounded-lg border border-border bg-card p-4">
                    <form
                        onSubmit={handleFilter}
                        className="grid grid-cols-1 gap-4 md:grid-cols-6 lg:grid-cols-12"
                    >
                        <div className="col-span-1 md:col-span-2 lg:col-span-3">
                            <label className="mb-1 block text-xs tracking-wide text-muted-foreground">
                                Buscar
                            </label>
                            <Input
                                value={data.search}
                                onChange={(e) =>
                                    setData('search', e.target.value)
                                }
                                placeholder="Usuario, descripción..."
                            />
                        </div>

                        <div className="col-span-1 md:col-span-2 lg:col-span-2">
                            <label className="mb-1 block text-xs tracking-wide text-muted-foreground">
                                Módulo
                            </label>
                            <Select
                                value={data.log_name}
                                onValueChange={(val) =>
                                    setData('log_name', val)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Módulo" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos</SelectItem>
                                    {options.logNames.map((name) => (
                                        <SelectItem key={name} value={name}>
                                            {name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="col-span-1 md:col-span-2 lg:col-span-2">
                            <label className="mb-1 block text-xs tracking-wide text-muted-foreground">
                                Evento
                            </label>
                            <Select
                                value={data.event}
                                onValueChange={(val) => setData('event', val)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Evento" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos</SelectItem>
                                    {options.events.map((ev) => (
                                        <SelectItem key={ev} value={ev}>
                                            {ev}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="col-span-1 md:col-span-3 lg:col-span-2">
                            <label className="mb-1 block text-xs tracking-wide text-muted-foreground">
                                Desde
                            </label>
                            <Input
                                type="date"
                                value={data.date_from}
                                onChange={(e) =>
                                    setData('date_from', e.target.value)
                                }
                            />
                        </div>

                        <div className="col-span-1 md:col-span-3 lg:col-span-2">
                            <label className="mb-1 block text-xs tracking-wide text-muted-foreground">
                                Hasta
                            </label>
                            <Input
                                type="date"
                                value={data.date_to}
                                onChange={(e) =>
                                    setData('date_to', e.target.value)
                                }
                            />
                        </div>

                        <div className="col-span-1 flex items-end justify-center gap-2 md:col-span-6 lg:col-span-1 lg:justify-end">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={clearFilters}
                                title="Limpiar filtros"
                            >
                                ✕
                            </Button>
                            <Button type="submit">Filtrar</Button>
                        </div>
                    </form>
                </div>

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
                                            className={`rounded-full px-2 py-1 text-xs font-semibold tracking-wider uppercase ${getEventBadge(log.event)}`}
                                        >
                                            {log.event || 'default'}
                                        </span>
                                    </td>
                                    <td className="max-w-[200px] p-3 wrap-break-word text-foreground">
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
