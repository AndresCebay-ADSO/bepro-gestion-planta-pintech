import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus, Search, ShoppingCart } from 'lucide-react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Pagination from '@/components/ui/pagination';
import {
    index as salesOrdersIndex,
    create as salesOrdersCreate,
    show as salesOrdersShow,
} from '@/routes/sales-orders';
import type { PaginationLink } from '@/types/ui';

interface SalesOrderRow {
    id: number;
    client: { id: number; business_name: string };
    creator?: { name: string } | null;
    status: string;
    status_label: string;
    priority: string;
    priority_label: string;
    required_date: string | null;
    estimated_delivery_date: string | null;
    items_count: number;
    created_at: string;
}

type Props = {
    orders: {
        data: SalesOrderRow[];
        links: PaginationLink[];
    };
    filters: {
        search?: string;
        status?: string;
        priority?: string;
    };
    can: {
        create: boolean;
        manage: boolean;
    };
};

const statusColors: Record<string, string> = {
    pending:
        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    in_progress:
        'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    ready: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    delivered:
        'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
    cancelled: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400',
};

const priorityColors: Record<string, string> = {
    low: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    medium: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    high: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
};

export default function SalesOrdersIndex({ orders, filters, can }: Props) {
    const { data, setData, get } = useForm({
        search: filters.search ?? '',
        status: filters.status ?? '',
        priority: filters.priority ?? '',
    });

    const handleSearch = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        get(salesOrdersIndex().url, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const handleFilterChange = (field: string, value: string) => {
        setData(field as 'search' | 'status' | 'priority', value);
        router.get(
            salesOrdersIndex().url,
            { ...data, [field]: value },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    return (
        <>
            <Head title={can.manage ? 'Gestión de Pedidos' : 'Pedidos'} />

            <div className="space-y-4 p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
                            {can.manage ? 'Gestión de Pedidos' : 'Pedidos'}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {can.manage
                                ? 'Administración de todos los pedidos de clientes.'
                                : 'Gestión de pedidos de clientes.'}
                        </p>
                    </div>
                    {can.create && (
                        <Button asChild>
                            <Link href={salesOrdersCreate().url}>
                                <Plus className="mr-2 h-4 w-4" />
                                Nuevo Pedido
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="flex flex-wrap items-center gap-4">
                    <form
                        onSubmit={handleSearch}
                        className="relative w-full max-w-sm"
                    >
                        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Buscar por cliente..."
                            value={data.search}
                            onChange={(e) => setData('search', e.target.value)}
                            className="pl-10"
                        />
                    </form>

                    <select
                        value={data.status}
                        onChange={(e) =>
                            handleFilterChange('status', e.target.value)
                        }
                        className="h-9 rounded-md border border-input bg-background px-3 text-sm ring-offset-background"
                    >
                        <option value="">Todos los estados</option>
                        <option value="pending">Pendiente</option>
                        <option value="in_progress">En progreso</option>
                        <option value="ready">Lista</option>
                        <option value="delivered">Entregada</option>
                        <option value="cancelled">Cancelada</option>
                    </select>

                    <select
                        value={data.priority}
                        onChange={(e) =>
                            handleFilterChange('priority', e.target.value)
                        }
                        className="h-9 rounded-md border border-input bg-background px-3 text-sm ring-offset-background"
                    >
                        <option value="">Todas las prioridades</option>
                        <option value="low">Baja</option>
                        <option value="medium">Media</option>
                        <option value="high">Alta</option>
                    </select>
                </div>

                {orders.data.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-border py-12">
                        <ShoppingCart className="mb-4 h-12 w-12 text-muted-foreground" />
                        <p className="text-lg font-medium text-muted-foreground">
                            No hay pedidos registrados
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {can.create
                                ? 'Crea tu primer pedido haciendo clic en "Nuevo Pedido"'
                                : 'Aún no hay pedidos en el sistema.'}
                        </p>
                    </div>
                ) : (
                    <>
                        <div className="rounded border border-border bg-card">
                            <table className="w-full text-sm">
                                <thead className="border-b border-border bg-muted/50">
                                    <tr>
                                        <th className="p-3 text-left">Nº</th>
                                        <th className="p-3 text-left">
                                            Cliente
                                        </th>
                                        {can.manage && (
                                            <th className="p-3 text-left">
                                                Comercial
                                            </th>
                                        )}
                                        <th className="p-3 text-left">
                                            Prioridad
                                        </th>
                                        <th className="p-3 text-left">
                                            Estado
                                        </th>
                                        <th className="p-3 text-left">
                                            Fecha requerida
                                        </th>
                                        <th className="p-3 text-left">
                                            Fecha estimada
                                        </th>
                                        <th className="p-3 text-left">Items</th>
                                        <th className="p-3 text-right">
                                            Acciones
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {orders.data.map((order) => (
                                        <tr
                                            key={order.id}
                                            className="border-b border-border/50"
                                        >
                                            <td className="p-3 font-mono text-muted-foreground">
                                                #{order.id}
                                            </td>
                                            <td className="p-3 font-medium">
                                                {order.client.business_name}
                                            </td>
                                            {can.manage && (
                                                <td className="p-3 text-muted-foreground">
                                                    {order.creator?.name ?? '-'}
                                                </td>
                                            )}
                                            <td className="p-3">
                                                <span
                                                    className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${priorityColors[order.priority] ?? 'bg-gray-100 text-gray-800'}`}
                                                >
                                                    {order.priority_label}
                                                </span>
                                            </td>
                                            <td className="p-3">
                                                <span
                                                    className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${statusColors[order.status] ?? 'bg-gray-100 text-gray-800'}`}
                                                >
                                                    {order.status_label}
                                                </span>
                                            </td>
                                            <td className="p-3 text-muted-foreground">
                                                {order.required_date ?? '-'}
                                            </td>
                                            <td className="p-3 text-muted-foreground">
                                                {order.estimated_delivery_date ??
                                                    '-'}
                                            </td>
                                            <td className="p-3 text-muted-foreground">
                                                {order.items_count} producto(s)
                                            </td>
                                            <td className="p-3 text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={
                                                            salesOrdersShow(
                                                                order.id,
                                                            ).url
                                                        }
                                                    >
                                                        Ver
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="mt-4 flex justify-center">
                            <Pagination links={orders.links} />
                        </div>
                    </>
                )}
            </div>
        </>
    );
}
