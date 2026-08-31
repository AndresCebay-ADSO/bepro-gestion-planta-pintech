import { Head, Link } from '@inertiajs/react';
import { Plus, Users } from 'lucide-react';

import { DataTableFilters } from '@/components/data-table-filters';
import { Button } from '@/components/ui/button';
import Pagination from '@/components/ui/pagination';
import { useFilters } from '@/hooks/use-filters';
import {
    index as clientsIndex,
    create as clientsCreate,
    edit as clientsEdit,
} from '@/routes/clients';
import type { PaginationLink } from '@/types/ui';

interface ClientRow {
    id: number;
    business_name: string;
    nit: string | null;
    contact_name: string | null;
    phone: string | null;
    shipping_address: string | null;
}

type Props = {
    clients: {
        data: ClientRow[];
        links: PaginationLink[];
    };
    filters: Record<string, string | null | undefined>;
    can: {
        edit: boolean;
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
        },
    });

    return (
        <>
            <Head title="Clientes" />

            <div className="space-y-4 p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
                            Clientes
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Gestión de clientes.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={clientsCreate().url}>
                            <Plus className="mr-2 h-4 w-4" />
                            Nuevo Cliente
                        </Link>
                    </Button>
                </div>

                <DataTableFilters
                    fields={[
                        {
                            type: 'text',
                            name: 'search',
                            label: 'Buscar',
                            placeholder: 'Buscar por razón social o NIT...',
                        },
                    ]}
                    filters={filterState}
                    onFilter={setFilter}
                    onClear={clearFilters}
                />

                {clients.data.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-border py-12">
                        <Users className="mb-4 h-12 w-12 text-muted-foreground" />
                        <p className="text-lg font-medium text-muted-foreground">
                            No hay clientes registrados
                        </p>
                    </div>
                ) : (
                    <>
                        <div className="rounded border border-border bg-card">
                            <table className="w-full text-sm">
                                <thead className="border-b border-border bg-muted/50">
                                    <tr>
                                        <th className="p-3 text-left">
                                            Razón social
                                        </th>
                                        <th className="p-3 text-left">NIT</th>
                                        <th className="p-3 text-left">
                                            Contacto
                                        </th>
                                        <th className="p-3 text-left">
                                            Teléfono
                                        </th>
                                        <th className="p-3 text-left">
                                            Dirección
                                        </th>
                                        {can.edit && (
                                            <th className="p-3 text-right">
                                                Acciones
                                            </th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {clients.data.map((client) => (
                                        <tr
                                            key={client.id}
                                            className="border-b border-border/50"
                                        >
                                            <td className="p-3 font-medium">
                                                {client.business_name}
                                            </td>
                                            <td className="p-3 text-muted-foreground">
                                                {client.nit ?? '-'}
                                            </td>
                                            <td className="p-3 text-muted-foreground">
                                                {client.contact_name ?? '-'}
                                            </td>
                                            <td className="p-3 text-muted-foreground">
                                                {client.phone ?? '-'}
                                            </td>
                                            <td className="max-w-xs truncate p-3 text-muted-foreground">
                                                {client.shipping_address ?? '-'}
                                            </td>
                                            {can.edit && (
                                                <td className="p-3 text-right">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={
                                                                clientsEdit(
                                                                    client.id,
                                                                ).url
                                                            }
                                                        >
                                                            Editar
                                                        </Link>
                                                    </Button>
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="mt-4 flex justify-center">
                            <Pagination links={clients.links} />
                        </div>
                    </>
                )}
            </div>
        </>
    );
}
