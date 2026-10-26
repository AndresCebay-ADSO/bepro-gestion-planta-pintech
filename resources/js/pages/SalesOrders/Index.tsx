import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { ComponentProps } from 'react';

import { DataListing } from '@/components/data-listing';
import type { DataListingColumn } from '@/components/data-listing';
import { DataTableFilters } from '@/components/data-table-filters';
import { FormattedDate } from '@/components/formatted-date';
import { FormattedNumber } from '@/components/formatted-number';
import { PageHeader } from '@/components/page-header';
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

    const title = can.manage ? 'Gestión de pedidos' : 'Pedidos';

    const columns: DataListingColumn<SalesOrderRow>[] = [
        {
            key: 'number',
            header: 'Nº',
            cell: (order) => `#${order.id}`,
            mobile: 'title',
            className: 'font-mono',
            cardClassName: 'font-mono',
        },
        {
            key: 'client',
            header: 'Cliente',
            cell: (order) => order.client.business_name,
            mobile: 'subtitle',
        },
        ...(can.manage
            ? [
                  {
                      key: 'creator',
                      header: 'Comercial',
                      cell: (order: SalesOrderRow) =>
                          order.creator?.name ?? '-',
                      mobile: 'meta' as const,
                      className: 'text-muted-foreground',
                  },
              ]
            : []),
        {
            key: 'priority',
            header: 'Prioridad',
            cell: (order) => <PriorityBadge value={order.priority} />,
            mobile: 'meta',
        },
        {
            key: 'status',
            header: 'Estado',
            cell: (order) => (
                <StatusBadge enum="SalesOrderStatus" value={order.status} />
            ),
            mobile: 'status',
        },
        {
            key: 'required_date',
            header: 'Fecha requerida',
            cell: (order) => (
                <FormattedDate
                    value={order.required_date}
                    format="short"
                    emptyValue="-"
                />
            ),
            mobile: 'meta',
            className: 'text-muted-foreground',
        },
        {
            key: 'estimated_delivery_date',
            header: 'Fecha estimada',
            cell: (order) => (
                <FormattedDate
                    value={order.estimated_delivery_date}
                    format="short"
                    emptyValue="-"
                />
            ),
            mobile: 'meta',
            className: 'text-muted-foreground',
        },
        {
            key: 'items',
            header: 'Productos',
            cell: (order) => <FormattedNumber value={order.items_count} />,
            mobile: 'hidden',
            align: 'right',
            className: 'text-muted-foreground',
        },
    ];

    return (
        <>
            <Head title={title} />

            <div className="space-y-4 p-6">
                <PageHeader
                    title={title}
                    description={
                        can.manage
                            ? 'Administración de todos los pedidos de clientes.'
                            : 'Gestión de pedidos de clientes.'
                    }
                    actions={
                        can.create && (
                            <Button asChild>
                                <Link href={salesOrdersCreate().url}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Nuevo pedido
                                </Link>
                            </Button>
                        )
                    }
                />

                <DataTableFilters
                    fields={filterFields}
                    filters={filterState}
                    onFilter={setFilter}
                    onFilterImmediate={setFilterImmediate}
                    onClear={clearFilters}
                />

                <DataListing
                    rows={orders.data}
                    rowKey={(order) => order.id}
                    rowHref={(order) => salesOrdersShow(order.id).url}
                    columns={columns}
                    empty={
                        <>
                            <p className="font-medium">
                                No hay pedidos registrados
                            </p>
                            <p>
                                {can.create
                                    ? 'Crea tu primer pedido con «Nuevo pedido».'
                                    : 'Aún no hay pedidos en el sistema.'}
                            </p>
                        </>
                    }
                />

                <div className="mt-4 flex justify-center">
                    <Pagination links={orders.links} />
                </div>
            </div>
        </>
    );
}
