import { Head, Link } from '@inertiajs/react';
import type { ComponentProps } from 'react';

import { DataListing } from '@/components/data-listing';
import type { DataListingColumn } from '@/components/data-listing';
import { DataTableFilters } from '@/components/data-table-filters';
import { FormattedDate } from '@/components/formatted-date';
import { FormattedNumber } from '@/components/formatted-number';
import { PageHeader } from '@/components/page-header';
import Pagination from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useFilters } from '@/hooks/use-filters';
import { withReturnTo } from '@/lib/navigation';
import {
    show as productionOrderShow,
    create as productionOrderCreate,
    index as productionOrderIndex,
} from '@/routes/production-orders';
import type { Paginated } from '@/types/ui';

type ProductionOrderItem = {
    id: number;
    order_number: string;
    lot_number: number;
    /** Nombre con el color que pidió el cliente (3.4). */
    product_display_name: string;
    product?: { name: string } | null;
    formula?: { version: number } | null;
    warehouse?: { name: string } | null;
    quantity: string | number;
    status:
        | 'pending'
        | 'in_progress'
        | 'pending_review'
        | 'completed'
        | 'cancelled';
    planned_date: string;
    completion_date: string | null;
    created_at: string;
};

type Props = {
    orders: Paginated<ProductionOrderItem>;
    filters: {
        search?: string;
        status?: string;
        date_from?: string;
        date_to?: string;
        completed_from?: string;
        completed_to?: string;
    };
    can: {
        create: boolean;
    };
    statusOptions: { value: string; label: string }[];
};

export default function ProductionOrdersIndex({
    orders,
    filters,
    can,
    statusOptions,
}: Props) {
    const {
        filters: filterState,
        setFilter,
        setFilterImmediate,
        clearFilters,
    } = useFilters({
        routeUrl: productionOrderIndex().url,
        initialFilters: filters,
    });

    const filterFields: ComponentProps<typeof DataTableFilters>['fields'] = [
        {
            type: 'text',
            name: 'search',
            label: 'Buscar',
            placeholder: 'Buscar por orden, lote, producto o color…',
        },
        {
            type: 'select',
            name: 'status',
            label: 'Estado',
            options: statusOptions,
        },
        {
            type: 'date-range',
            nameFrom: 'date_from',
            nameTo: 'date_to',
            label: 'Fecha de creación',
        },
        {
            type: 'date-range',
            nameFrom: 'completed_from',
            nameTo: 'completed_to',
            label: 'Fecha de finalización',
        },
    ];

    const columns: DataListingColumn<ProductionOrderItem>[] = [
        {
            key: 'order_number',
            header: 'Orden',
            cell: (order) => order.order_number,
            mobile: 'title',
            className: 'font-mono',
            cardClassName: 'font-mono',
        },
        {
            key: 'lot_number',
            header: 'Lote',
            cell: (order) => order.lot_number,
            mobile: 'meta',
            className: 'font-mono text-muted-foreground',
            cardClassName: 'font-mono',
        },
        {
            key: 'product',
            header: 'Producto',
            cell: (order) => (
                <>
                    <div className="font-medium text-foreground">
                        {order.product_display_name}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        Fórmula v{order.formula?.version ?? 1}
                    </div>
                </>
            ),
            mobile: 'subtitle',
        },
        {
            key: 'quantity',
            header: 'Cant. planificada',
            cell: (order) => (
                <>
                    <FormattedNumber value={order.quantity} /> gal
                </>
            ),
            mobile: 'primary',
            align: 'right',
            className: 'font-medium whitespace-nowrap',
        },
        {
            key: 'status',
            header: 'Estado',
            cell: (order) => (
                <StatusBadge
                    enum="ProductionOrderStatus"
                    value={order.status}
                />
            ),
            mobile: 'status',
        },
        {
            key: 'dates',
            header: 'Fechas',
            cell: (order) => (
                <>
                    <div>
                        Plan: <FormattedDate value={order.planned_date} />
                    </div>
                    <div>
                        Creada: <FormattedDate value={order.created_at} />
                    </div>
                </>
            ),
            mobile: 'meta',
            className: 'whitespace-nowrap text-muted-foreground',
        },
        {
            key: 'completion_date',
            header: 'Fecha fin',
            cell: (order) =>
                order.completion_date ? (
                    <FormattedDate value={order.completion_date} />
                ) : null,
            mobile: 'meta',
            className: 'whitespace-nowrap text-muted-foreground',
        },
    ];

    return (
        <>
            <Head title="Órdenes de producción" />
            <div className="space-y-6 p-6">
                <PageHeader
                    title="Órdenes de producción"
                    description="Gestión y seguimiento de la fabricación de pinturas en Planta Cali."
                    actions={
                        can.create && (
                            <Button asChild>
                                <Link href={productionOrderCreate().url}>
                                    Nueva orden
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
                    rowHref={(order) =>
                        withReturnTo(
                            productionOrderShow({ production_order: order.id })
                                .url,
                        )
                    }
                    columns={columns}
                    empty="No hay órdenes de producción registradas."
                />

                <Pagination paginator={orders} />
            </div>
        </>
    );
}
