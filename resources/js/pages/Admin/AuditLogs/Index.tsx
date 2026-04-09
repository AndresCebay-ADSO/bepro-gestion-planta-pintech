import { Head, router } from '@inertiajs/react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { useState, FormEvent, useEffect } from 'react';
import AuditLogController from '@/actions/App/Http/Controllers/Admin/AuditLogController';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Causer = {
    id: number;
    name: string;
    email: string;
};

type ActivityLog = {
    id: number;
    log_name: string;
    description: string;
    event: string;
    subject_type: string;
    subject_id: number;
    causer_type: string | null;
    causer_id: number | null;
    causer: Causer | null;
    properties: Record<string, any>;
    created_at: string;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
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
    const [search, setSearch] = useState(filters.search ?? '');
    const [logName, setLogName] = useState(filters.log_name ?? '');
    const [eventFilter, setEventFilter] = useState(filters.event ?? '');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');

    const handleFilter = (e?: FormEvent) => {
        if (e) e.preventDefault();

        router.get(
            AuditLogController.index.url(),
            {
                search,
                log_name: logName !== 'all' ? logName : undefined,
                event: eventFilter !== 'all' ? eventFilter : undefined,
                date_from: dateFrom,
                date_to: dateTo,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const clearFilters = () => {
        setSearch('');
        setLogName('');
        setEventFilter('');
        setDateFrom('');
        setDateTo('');
        
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
        if (!properties || Object.keys(properties).length === 0) return '-';
        
        // Truncar para no romper UI
        const jsonStr = JSON.stringify(properties);
        if (jsonStr.length > 50) {
            return (
                <div className="relative group cursor-help">
                    <span className="truncate max-w-[150px] inline-block">{jsonStr}</span>
                    <div className="absolute hidden group-hover:block z-10 bg-popover text-popover-foreground border shadow-md p-2 rounded-md text-xs bottom-full mb-1 max-w-sm whitespace-pre-wrap left-0">
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

                {/* Filtros */}
                <div className="rounded-lg border border-border bg-card p-4">
                    <form onSubmit={handleFilter} className="grid grid-cols-1 gap-4 md:grid-cols-6 lg:grid-cols-12">
                        <div className="col-span-1 md:col-span-2 lg:col-span-3">
                            <label className="mb-1 block text-xs tracking-wide text-muted-foreground">Búsqueda</label>
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Usuario, descripción..."
                            />
                        </div>
                        
                        <div className="col-span-1 md:col-span-2 lg:col-span-2">
                            <label className="mb-1 block text-xs tracking-wide text-muted-foreground">Módulo</label>
                            <Select value={logName || 'all'} onValueChange={setLogName}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Módulo" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos</SelectItem>
                                    {options.logNames.map(name => (
                                        <SelectItem key={name} value={name}>{name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="col-span-1 md:col-span-2 lg:col-span-2">
                            <label className="mb-1 block text-xs tracking-wide text-muted-foreground">Evento</label>
                            <Select value={eventFilter || 'all'} onValueChange={setEventFilter}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Evento" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos</SelectItem>
                                    {options.events.map(ev => (
                                        <SelectItem key={ev} value={ev}>{ev}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="col-span-1 md:col-span-3 lg:col-span-2">
                            <label className="mb-1 block text-xs tracking-wide text-muted-foreground">Desde</label>
                            <Input
                                type="date"
                                value={dateFrom}
                                onChange={(e) => setDateFrom(e.target.value)}
                            />
                        </div>

                        <div className="col-span-1 md:col-span-3 lg:col-span-2">
                            <label className="mb-1 block text-xs tracking-wide text-muted-foreground">Hasta</label>
                            <Input
                                type="date"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                            />
                        </div>

                        <div className="col-span-1 md:col-span-6 lg:col-span-1 flex items-end justify-center lg:justify-end gap-2">
                            <Button type="button" variant="ghost" size="icon" onClick={clearFilters} title="Limpiar Filtros">
                                ✕
                            </Button>
                            <Button type="submit">
                                Filtrar
                            </Button>
                        </div>
                    </form>
                </div>

                {/* Tabla */}
                <div className="overflow-x-auto rounded-lg border border-border bg-card">
                    <table className="w-full text-sm">
                        <thead className="border-b border-border bg-muted/40">
                            <tr>
                                <th className="p-3 text-left font-medium">Fecha</th>
                                <th className="p-3 text-left font-medium">Causante</th>
                                <th className="p-3 text-left font-medium">Módulo</th>
                                <th className="p-3 text-center font-medium">Evento</th>
                                <th className="p-3 text-left font-medium">Descripción</th>
                                <th className="p-3 text-left font-medium">Detalles (Properties)</th>
                            </tr>
                        </thead>

                        <tbody>
                            {logs.data.map((log) => (
                                <tr
                                    key={log.id}
                                    className="border-b border-border/60 last:border-0 hover:bg-muted/30 transition"
                                >
                                    <td className="p-3 whitespace-nowrap text-muted-foreground text-xs">
                                        {format(new Date(log.created_at), "dd MMM yyyy, HH:mm", { locale: es })}
                                    </td>
                                    <td className="p-3">
                                        {log.causer ? (
                                            <div>
                                                <div className="font-medium">{log.causer.name}</div>
                                                <div className="text-xs text-muted-foreground">{log.causer.email}</div>
                                            </div>
                                        ) : (
                                            <span className="text-muted-foreground italic">Sistema / Anónimo</span>
                                        )}
                                    </td>
                                    <td className="p-3">
                                        <span className="px-2 py-1 rounded bg-secondary text-secondary-foreground text-xs uppercase tracking-wider">
                                            {log.log_name}
                                        </span>
                                    </td>
                                    <td className="p-3 text-center">
                                        <span className={`rounded-full px-2 py-1 text-xs font-semibold uppercase tracking-wider ${getEventBadge(log.event)}`}>
                                            {log.event || 'default'}
                                        </span>
                                    </td>
                                    <td className="p-3 text-foreground break-words max-w-[200px]">
                                        {log.description}
                                    </td>
                                    <td className="p-3 font-mono text-xs text-muted-foreground max-w-[200px]">
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
                                        No hay registros que coincidan con los filtros.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Paginación */}
                {logs.links.length > 3 && (
                    <div className="flex flex-wrap gap-2">
                        {logs.links.filter(l => !l.label.includes('Previous') && !l.label.includes('Next') && !l.label.includes('previous') && !l.label.includes('next')).map((link, index) => (
                            <Button
                                key={`${link.label}-${index}`}
                                type="button"
                                size="sm"
                                variant={link.active ? 'default' : 'outline'}
                                disabled={!link.url}
                                onClick={() => {
                                    if (link.url) {
                                        router.visit(link.url, {
                                            preserveScroll: true,
                                            preserveState: true,
                                        });
                                    }
                                }}
                                dangerouslySetInnerHTML={{
                                    __html: link.label,
                                }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
