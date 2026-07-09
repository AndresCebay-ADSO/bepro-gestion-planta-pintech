import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Search, Users } from 'lucide-react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Pagination from '@/components/ui/pagination';
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
    filters: {
        search?: string;
    };
    can: {
        edit: boolean;
    };
};

export default function ClientsIndex({ clients, filters, can }: Props) {
    const { data, setData, get } = useForm({
        search: filters.search ?? '',
    });

    const handleSearch = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        get(clientsIndex().url, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

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

                <form
                    onSubmit={handleSearch}
                    className="relative w-full max-w-sm"
                >
                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        placeholder="Buscar cliente..."
                        value={data.search}
                        onChange={(e) => setData('search', e.target.value)}
                        className="pl-10"
                    />
                </form>

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
