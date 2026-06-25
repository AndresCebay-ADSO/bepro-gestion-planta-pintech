import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import type { FormEvent } from 'react';
import AlertController from '@/actions/App/Http/Controllers/AlertController';

import { Button } from '@/components/ui/button';
import Pagination from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
        status: 'active' | 'resolved' | 'all';
        type: string;
        severity: string;
    };
    options: {
        types: Option[];
        severities: Option[];
    };
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

export default function AlertsIndex({
    alerts,
    filters,
    options,
    stats,
}: Props) {
    const { data, setData, get } = useForm({
        status: filters.status ?? 'active',
        type: filters.type ?? 'all',
        severity: filters.severity ?? 'all',
    });

    const flash = usePage<{
        flash?: { success?: string; error?: string };
    }>().props.flash;

    const handleFilter = (event?: FormEvent) => {
        event?.preventDefault();

        get(AlertController.index.url(), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const handleResolve = (alertId: number) => {
        router.patch(AlertController.resolve.url(alertId), undefined, {
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

                <form
                    onSubmit={handleFilter}
                    className="grid grid-cols-1 gap-3 md:grid-cols-4"
                >
                    <Select
                        value={data.status}
                        onValueChange={(value) => {
                            setData(
                                'status',
                                value as 'active' | 'resolved' | 'all',
                            );
                            handleFilter();
                        }}
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Estado" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="active">Activas</SelectItem>
                            <SelectItem value="resolved">Resueltas</SelectItem>
                            <SelectItem value="all">Todas</SelectItem>
                        </SelectContent>
                    </Select>

                    <Select
                        value={data.type}
                        onValueChange={(value) => {
                            setData('type', value);
                            handleFilter();
                        }}
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Tipo" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todos los tipos</SelectItem>
                            {options.types.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={data.severity}
                        onValueChange={(value) => {
                            setData('severity', value);
                            handleFilter();
                        }}
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Urgencia" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                Todas las urgencias
                            </SelectItem>
                            {options.severities.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Button type="submit" variant="outline">
                        Aplicar filtros
                    </Button>
                </form>

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
