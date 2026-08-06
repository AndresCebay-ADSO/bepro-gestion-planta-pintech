import { Head, Link, router, useForm } from '@inertiajs/react';
import { FlaskConical, Plus, Search } from 'lucide-react';
import type { FormEvent } from 'react';

import StatusBadge from '@/components/paint-development-requests/StatusBadge';
import { TableActions } from '@/components/table-actions';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Pagination from '@/components/ui/pagination';
import {
    create as requestsCreate,
    edit as requestsEdit,
    index as requestsIndex,
    show as requestsShow,
} from '@/routes/paint-development-requests';
import type { PaginationLink } from '@/types/ui';

type RequestRow = {
    id: number;
    request_number: number;
    client_name: string | null;
    creator?: { name: string } | null;
    status: string;
    status_label: string;
    project_name: string;
    sample_due_date: string | null;
    city: string;
    created_at: string;
};

type Props = {
    requests: {
        data: RequestRow[];
        links: PaginationLink[];
    };
    filters: {
        search?: string;
        status?: string;
    };
    statusOptions: { id: string | number; label: string }[];
    can: {
        create: boolean;
    };
};

export default function PaintDevelopmentRequestsIndex({
    requests,
    filters,
    statusOptions,
    can,
}: Props) {
    const { data, setData, get } = useForm({
        search: filters.search ?? '',
        status: filters.status ?? '',
    });

    const handleSearch = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        get(requestsIndex().url, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const handleStatusChange = (value: string) => {
        setData('status', value);
        router.get(
            requestsIndex().url,
            { ...data, status: value },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    return (
        <>
            <Head title="Desarrollo de pinturas" />

            <div className="space-y-4 p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
                            Desarrollo de pinturas
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Solicitudes de desarrollo de nuevos productos
                        </p>
                    </div>
                    {can.create && (
                        <Button asChild>
                            <Link href={requestsCreate().url}>
                                <Plus className="mr-2 h-4 w-4" />
                                Nueva solicitud
                            </Link>
                        </Button>
                    )}
                </div>

                <form
                    onSubmit={handleSearch}
                    className="flex flex-col gap-3 md:flex-row md:items-center"
                >
                    <div className="relative flex-1">
                        <Search className="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" />
                        <Input
                            value={data.search}
                            onChange={(e) => setData('search', e.target.value)}
                            placeholder="Buscar por número, proyecto o cliente..."
                            className="pl-9"
                        />
                    </div>
                    <select
                        value={data.status}
                        onChange={(e) => handleStatusChange(e.target.value)}
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">Todos los estados</option>
                        {statusOptions.map((opt) => (
                            <option key={opt.id} value={String(opt.id)}>
                                {opt.label}
                            </option>
                        ))}
                    </select>
                    <Button type="submit" variant="outline">
                        Buscar
                    </Button>
                </form>

                <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b border-border bg-muted/30">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    Número
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Proyecto
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Cliente
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Estado
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Fecha muestra
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {requests.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        <FlaskConical className="mx-auto mb-2 h-8 w-8 opacity-40" />
                                        No hay solicitudes registradas.
                                    </td>
                                </tr>
                            ) : (
                                requests.data.map((req) => (
                                    <tr
                                        key={req.id}
                                        className="border-b border-border last:border-0"
                                    >
                                        <td className="px-4 py-3 font-mono text-xs">
                                            {req.request_number}
                                        </td>
                                        <td className="px-4 py-3">
                                            {req.project_name}
                                        </td>
                                        <td className="px-4 py-3">
                                            {req.client_name ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusBadge
                                                status={req.status}
                                                label={req.status_label}
                                            />
                                        </td>
                                        <td className="px-4 py-3">
                                            {req.sample_due_date ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <TableActions
                                                actions={{
                                                    view: true,
                                                    edit:
                                                        req.status === 'draft',
                                                    delete: false,
                                                }}
                                                onView={() =>
                                                    router.get(
                                                        requestsShow(req.id)
                                                            .url,
                                                    )
                                                }
                                                onEdit={
                                                    req.status === 'draft'
                                                        ? () =>
                                                              router.get(
                                                                  requestsEdit(
                                                                      req.id,
                                                                  ).url,
                                                              )
                                                        : undefined
                                                }
                                            />
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination links={requests.links} />
            </div>
        </>
    );
}
