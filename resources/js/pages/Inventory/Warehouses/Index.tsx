import { Head, useForm, Link, router, usePage } from '@inertiajs/react';
import { Users } from 'lucide-react';
import type { FormEvent } from 'react';
import { TableActions } from '@/components/table-actions';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import Pagination from '@/components/ui/pagination';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import {
    index as warehousesIndex,
    create as warehousesCreate,
    show as warehousesShow,
    edit as warehousesEdit,
    destroy as warehousesDestroy,
} from '@/routes/warehouses';

import { form as warehousesAssignUsersForm } from '@/routes/warehouses/assign-users';
import type { PaginationLink } from '@/types/ui';

type WarehouseRow = {
    id: number;
    name: string;
    city: string;
    address: string | null;
    type: 'factory' | 'storage';
    is_active: boolean;
    users_count: number;
    can: {
        view: boolean;
        update: boolean;
        delete: boolean;
    };
};


type Props = {
    warehouses: {
        data: WarehouseRow[];
        links: PaginationLink[];
    };
    filters: {
        search: string;
    };
    can: {
        create: boolean;
    };
};

export default function WarehousesIndex({ warehouses, filters, can }: Props) {
    const { data, setData, get } = useForm({
        search: filters.search ?? '',
    });
    const flash = usePage<{ flash?: { success?: string; error?: string } }>().props.flash;


    const handleSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        get(warehousesIndex({ query: { search: data.search } }).url, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const handleDelete = (id: number) => {
        if (!window.confirm('Are you sure you want to delete this warehouse?')) {
            return;
        }

        router.delete(warehousesDestroy(id).url, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Bodegas" />

            <div className="space-y-6 p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">Warehouses</h1>
                        <p className="text-sm text-muted-foreground">Manage and consult operating warehouses.</p>
                    </div>
                    {can.create && (
                        <Button asChild>
                            <Link href={warehousesCreate().url}>New Warehouse</Link>
                        </Button>
                    )}
                </div>

                {flash?.success && (
                    <div className="rounded-md border border-emerald-500/25 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-700 dark:text-emerald-300">
                        {flash.success}
                    </div>
                )}

                {flash?.error && (
                    <div className="rounded-md border border-destructive/30 bg-destructive/10 px-4 py-2 text-sm text-destructive">
                        {flash.error}
                    </div>
                )}

                <form onSubmit={handleSearch} className="flex flex-col gap-2 sm:flex-row">
                    <Input
                        value={data.search}
                        onChange={(event) => setData('search', event.target.value)}
                        placeholder="Search by name, city or address..."
                        className="sm:max-w-md"
                    />
                    <Button type="submit" variant="outline">
                        Search
                    </Button>
                </form>

                <div className="overflow-x-auto rounded-lg border border-border bg-card">
                    <table className="w-full text-sm">
                        <thead className="border-b border-border bg-muted/40">
                            <tr>
                                <th className="p-3 text-left font-medium text-foreground">Name</th>
                                <th className="p-3 text-left font-medium text-foreground">City</th>
                                <th className="p-3 text-left font-medium text-foreground">Address</th>
                                <th className="p-3 text-left font-medium text-foreground">Type</th>
                                <th className="p-3 text-left font-medium text-foreground">Status</th>
                                <th className="p-3 text-left font-medium text-foreground">Users</th>
                                <th className="p-3 text-right font-medium text-foreground">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {warehouses.data.map((warehouse) => (
                                <tr key={warehouse.id} className="border-b border-border/60 last:border-0">
                                    <td className="p-3 text-foreground">{warehouse.name}</td>
                                    <td className="p-3 text-muted-foreground">{warehouse.city}</td>
                                    <td className="p-3 text-muted-foreground">{warehouse.address ?? '-'}</td>
                                    <td className="p-3">
                                        <Badge variant={warehouse.type === 'factory' ? 'default' : 'secondary'}>
                                            {warehouse.type === 'factory' ? 'Fábrica' : 'Bodega'}
                                        </Badge>
                                    </td>
                                    <td className="p-3">
                                        <span
                                            className={warehouse.is_active
                                                ? 'rounded-full bg-emerald-500/15 px-2 py-1 text-xs font-medium text-emerald-600 dark:text-emerald-300'
                                                : 'rounded-full bg-slate-500/15 px-2 py-1 text-xs font-medium text-slate-600 dark:text-slate-300'}
                                        >
                                            {warehouse.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </td>
                                    <td className="p-3 text-muted-foreground">{warehouse.users_count}</td>
                                    <td className="p-3 text-right">
                                        <TableActions
                                            permissions={{ view: warehouse.can.view, edit: warehouse.can.update, delete: warehouse.can.delete }}
                                            onView={() => router.get(warehousesShow(warehouse.id).url)}
                                            onEdit={() => router.get(warehousesEdit(warehouse.id).url)}
                                            onDelete={() => handleDelete(warehouse.id)}
                                        >
                                            {warehouse.can.update && (
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Button
                                                            variant="outline"
                                                            size="icon"
                                                            className="h-8 w-8"
                                                            asChild
                                                        >
                                                            <Link href={warehousesAssignUsersForm(warehouse.id).url}>
                                                                <Users className="h-4 w-4" />
                                                                <span className="sr-only">Asignar usuarios</span>
                                                            </Link>
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>Asignar usuarios</TooltipContent>
                                                </Tooltip>
                                            )}
                                        </TableActions>
                                    </td>
                                </tr>
                            ))}
                            {warehouses.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="p-8 text-center text-sm text-muted-foreground">
                                        No warehouses found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex justify-center">
                    <Pagination links={warehouses.links} />
                </div>
            </div>
        </>
    );
}

