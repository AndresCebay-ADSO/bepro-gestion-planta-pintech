import { Head, Link } from '@inertiajs/react';
import { Plus, ShoppingCart } from 'lucide-react';
import type { ComponentProps } from 'react';

import { DataTableFilters } from '@/components/data-table-filters';
import { FormattedDate } from '@/components/formatted-date';
import { PriorityBadge } from '@/components/priority-badge';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import Pagination from '@/components/ui/pagination';
import { useFilters } from '@/hooks/use-filters';
import {
    create as salesOrdersCreate,
    index as salesOrdersIndex,
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
    filters: Record<string, string | null | undefined>;
    can: {
        create: boolean;
        manage: boolean;
    };
    statusOptions: { value: string; label: string }[];
    priorityOptions: { value: string; label: string }[];
};

export default function SalesOrdersIndex({
    orders,
    filters,
    can,
    statusOptions,
    priorityOptions,
}: Props) {
    const {
        filters: filterState,
        setFilter,
        setFilterImmediate,
        clearFilters,
    } = useFilters({
        routeUrl: salesOrdersIndex().url,
        initialFilters: filters,
    });

    const filterFields: ComponentProps<typeof DataTableFilters>['fields'] = [
        {
            type: 'text',
            name: 'search',
            label: 'Buscar',
            placeholder: 'Buscar por cliente o número de pedido...',
        },
        {
            type: 'select',
            name: 'status',
            label: 'Estado',
            options: statusOptions,
        },
        {
            type: 'select',
            name: 'priority',
            label: 'Prioridad',
            options: priorityOptions,
        },
        {
            type: 'date-range',
            nameFrom: 'date_from',
            nameTo: 'date_to',
            label: 'Fecha requerida',
        },
    ];

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

                <DataTableFilters
                    fields={filterFields}
                    filters={filterState}
                    onFilter={setFilter}
                    onFilterImmediate={setFilterImmediate}
                    onClear={clearFilters}
                />

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
                                                <PriorityBadge
                                                    value={order.priority}
                                                />
                                            </td>
                                            <td className="p-3">
                                                <StatusBadge
                                                    enum="SalesOrderStatus"
                                                    value={order.status}
                                                />
                                            </td>
                                            <td className="p-3 text-muted-foreground">
                                                <FormattedDate
                                                    value={order.required_date}
                                                    format="short"
                                                    emptyValue="-"
                                                />
                                            </td>
                                            <td className="p-3 text-muted-foreground">
                                                <FormattedDate
                                                    value={
                                                        order.estimated_delivery_date
                                                    }
                                                    format="short"
                                                    emptyValue="-"
                                                />
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
