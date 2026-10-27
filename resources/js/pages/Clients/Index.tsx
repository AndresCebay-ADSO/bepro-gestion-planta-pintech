import { Head, Link, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';

import { ActiveBadge } from '@/components/active-badge';
import { DataListing } from '@/components/data-listing';
import type { DataListingColumn } from '@/components/data-listing';
import { DataTableFilters } from '@/components/data-table-filters';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import Pagination from '@/components/ui/pagination';
import { useFilters } from '@/hooks/use-filters';
import {
    index as clientsIndex,
    create as clientsCreate,
    edit as clientsEdit,
    destroy as clientsDestroy,
} from '@/routes/clients';
import type { PaginationLink } from '@/types/ui';

interface ClientRow {
    id: number;
    business_name: string;
    nit: string | null;
    contact_name: string | null;
    phone: string | null;
    shipping_address: string | null;
    is_active: boolean;
}

type Props = {
    clients: {
        data: ClientRow[];
        links: PaginationLink[];
    };
    filters: Record<string, string | null | undefined>;
    can: {
        edit: boolean;
        delete: boolean;
    };
};

export default function ClientsIndex({ clients, filters, can }: Props) {
    const {
        filters: filterState,
        setFilter,
        clearFilters,
    } = useFilters({
        routeUrl: clientsIndex().url,
        initialFilters: {
            search: filters.search ?? '',
            status: filters.status ?? '',
        },
    });

    const columns: DataListingColumn<ClientRow>[] = [
        {
            key: 'business_name',
            header: 'Razón social',
            cell: (client) => client.business_name,
            mobile: 'title',
        },
        {
            key: 'nit',
            header: 'NIT',
            cell: (client) => client.nit ?? '-',
            mobile: 'meta',
            className: 'text-muted-foreground',
        },
        {
            key: 'contact_name',
            header: 'Contacto',
            cell: (client) => client.contact_name ?? '-',
            mobile: 'meta',
            className: 'text-muted-foreground',
        },
        {
            key: 'phone',
            header: 'Teléfono',
            cell: (client) => client.phone ?? '-',
            mobile: 'meta',
            className: 'text-muted-foreground',
        },
        {
            key: 'shipping_address',
            header: 'Dirección',
            // `max-w-xs` no limita un <td>: se corta en un bloque, y solo desde md (la tabla).
            // La tarjeta del celular la muestra entera.
            cell: (client) => (
                <span
                    className="block md:max-w-xs md:truncate"
                    title={client.shipping_address ?? undefined}
                >
                    {client.shipping_address ?? '-'}
                </span>
            ),
            mobile: 'meta',
            className: 'text-muted-foreground',
        },
        {
            key: 'status',
            header: 'Estado',
            cell: (client) => <ActiveBadge isActive={client.is_active} />,
            mobile: 'status',
        },
    ];

    return (
        <>
            <Head title="Clientes" />

            <div className="space-y-6 p-6">
                <PageHeader
                    title="Clientes"
                    description="Gestión de clientes."
                    actions={
                        <Button asChild>
                            <Link href={clientsCreate().url}>
                                Nuevo cliente
                            </Link>
                        </Button>
                    }
                />

                <DataTableFilters
                    fields={[
                        {
                            type: 'text',
                            name: 'search',
                            label: 'Buscar',
                            placeholder: 'Buscar por razón social o NIT...',
                        },
                        {
                            type: 'select',
                            name: 'status',
                            label: 'Estado',
                            options: [
                                { value: 'active', label: 'Activos' },
                                { value: 'inactive', label: 'Inactivos' },
                            ],
                        },
                    ]}
                    filters={filterState}
                    onFilter={setFilter}
                    onClear={clearFilters}
                />

                {/* Clientes no tiene detalle: el nombre abre la edición. */}
                <DataListing
                    rows={clients.data}
                    rowKey={(client) => client.id}
                    rowHref={
                        can.edit
                            ? (client) => clientsEdit(client.id).url
                            : undefined
                    }
                    rowLabel={(client) => client.business_name}
                    columns={columns}
                    // El nombre ya abre la edición: el menú no la repite (DESIGN.md §9).
                    actions={(client) => [
                        // Eliminar solo borra clientes sin cotizaciones ni pedidos; si tienen, el servidor pide desactivarlos.
                        {
                            label: 'Eliminar',
                            icon: Trash2,
                            destructive: true,
                            onSelect: () => {
                                if (
                                    confirm(
                                        '¿Eliminar este cliente definitivamente? Solo es posible si no tiene cotizaciones ni pedidos.',
                                    )
                                ) {
                                    router.delete(
                                        clientsDestroy(client.id).url,
                                    );
                                }
                            },
                            hidden: !can.delete,
                        },
                    ]}
                    empty="No hay clientes registrados"
                />

                <div className="mt-4 flex justify-center">
                    <Pagination links={clients.links} />
                </div>
            </div>
        </>
    );
}
