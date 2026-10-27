import { Head, Link, router, usePage } from '@inertiajs/react';
import { Pencil, Trash2, Users } from 'lucide-react';

import { ActiveBadge } from '@/components/active-badge';
import { DataListing } from '@/components/data-listing';
import type { DataListingColumn } from '@/components/data-listing';
import { DataTableFilters } from '@/components/data-table-filters';
import { FormattedNumber } from '@/components/formatted-number';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import Pagination from '@/components/ui/pagination';
import { useFilters } from '@/hooks/use-filters';
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
    filters: Record<string, string | null | undefined>;
    can: {
        create: boolean;
    };
};

export default function WarehousesIndex({ warehouses, filters, can }: Props) {
    const {
        filters: filterState,
        setFilter,
        clearFilters,
    } = useFilters({
        routeUrl: warehousesIndex().url,
        initialFilters: {
            search: filters.search ?? '',
            status: filters.status ?? '',
        },
    });
    const flash = usePage().props.flash;

    const handleDelete = (id: number) => {
        if (
            !window.confirm(
                '¿Estás seguro de que quieres eliminar esta bodega?',
            )
        ) {
            return;
        }

        router.delete(warehousesDestroy(id).url, {
            preserveScroll: true,
        });
    };

    const columns: DataListingColumn<WarehouseRow>[] = [
        {
            key: 'name',
            header: 'Nombre',
            cell: (warehouse) => warehouse.name,
            mobile: 'title',
        },
        {
            key: 'city',
            header: 'Ciudad',
            cell: (warehouse) => warehouse.city,
            mobile: 'subtitle',
            className: 'text-muted-foreground',
        },
        {
            key: 'address',
            header: 'Dirección',
            // `max-w-xs` no limita un <td>: se corta en un bloque, y solo desde md (la tabla).
            cell: (warehouse) =>
                warehouse.address ? (
                    <span
                        className="block md:max-w-xs md:truncate"
                        title={warehouse.address}
                    >
                        {warehouse.address}
                    </span>
                ) : null,
            mobile: 'meta',
            className: 'text-muted-foreground',
        },
        {
            key: 'type',
            header: 'Tipo',
            cell: (warehouse) => (
                <StatusBadge enum="WarehouseType" value={warehouse.type} />
            ),
            mobile: 'meta',
        },
        {
            key: 'status',
            header: 'Estado',
            cell: (warehouse) => (
                <ActiveBadge isActive={warehouse.is_active} feminine />
            ),
            mobile: 'status',
        },
        {
            key: 'users',
            header: 'Usuarios',
            cell: (warehouse) => (
                <FormattedNumber value={warehouse.users_count} />
            ),
            mobile: 'meta',
            align: 'right',
            className: 'text-muted-foreground',
        },
    ];

    return (
        <>
            <Head title="Bodegas" />

            <div className="space-y-6 p-6">
                <PageHeader
                    title="Bodegas"
                    description="Administra y consulta las bodegas operativas."
                    actions={
                        can.create && (
                            <Button asChild>
                                <Link href={warehousesCreate().url}>
                                    Nueva bodega
                                </Link>
                            </Button>
                        )
                    }
                />

                {flash?.success && (
                    <div className="rounded-md border border-success/30 bg-success/15 px-4 py-2 text-sm text-success-text">
                        {flash.success}
                    </div>
                )}

                {flash?.error && (
                    <div className="rounded-md border border-destructive/30 bg-destructive/10 px-4 py-2 text-sm text-destructive">
                        {flash.error}
                    </div>
                )}

                <DataTableFilters
                    fields={[
                        {
                            type: 'text',
                            name: 'search',
                            label: 'Buscar',
                            placeholder:
                                'Buscar por nombre, ciudad o dirección...',
                        },
                        {
                            type: 'select',
                            name: 'status',
                            label: 'Estado',
                            options: [
                                { value: 'active', label: 'Activas' },
                                { value: 'inactive', label: 'Inactivas' },
                            ],
                        },
                    ]}
                    filters={filterState}
                    onFilter={setFilter}
                    onClear={clearFilters}
                />

                <DataListing
                    rows={warehouses.data}
                    rowKey={(warehouse) => warehouse.id}
                    rowHref={(warehouse) =>
                        warehouse.can.view
                            ? warehousesShow(warehouse.id).url
                            : undefined
                    }
                    rowLabel={(warehouse) => warehouse.name}
                    columns={columns}
                    actions={(warehouse) => [
                        {
                            label: 'Editar',
                            icon: Pencil,
                            href: warehousesEdit(warehouse.id).url,
                            hidden: !warehouse.can.update,
                        },
                        {
                            label: 'Asignar usuarios',
                            icon: Users,
                            href: warehousesAssignUsersForm(warehouse.id).url,
                            hidden: !warehouse.can.update,
                        },
                        {
                            label: 'Eliminar',
                            icon: Trash2,
                            destructive: true,
                            onSelect: () => handleDelete(warehouse.id),
                            hidden: !warehouse.can.delete,
                        },
                    ]}
                    empty="No se encontraron bodegas."
                />

                <div className="mt-4 flex justify-center">
                    <Pagination links={warehouses.links} />
                </div>
            </div>
        </>
    );
}
