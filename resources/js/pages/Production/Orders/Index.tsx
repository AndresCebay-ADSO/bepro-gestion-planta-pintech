import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { ComponentProps } from 'react';

import { DataTableFilters } from '@/components/data-table-filters';
import { FormattedDate } from '@/components/formatted-date';
import { TableActions } from '@/components/table-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import Pagination from '@/components/ui/pagination';
import { useFilters } from '@/hooks/use-filters';
import { withReturnTo } from '@/lib/navigation';
import {
    show as productionOrderShow,
    create as productionOrderCreate,
    index as productionOrderIndex,
} from '@/routes/production-orders';
import type { PaginationLink } from '@/types/ui';

type ProductionOrderItem = {
    id: number;
    order_number: string;
    lot_number?: number | null;
    product?: { code: string; name: string } | null;
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
    orders: {
        data: ProductionOrderItem[];
        links: PaginationLink[];
    };
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
            placeholder: 'Buscar por orden, lote o producto…',
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

    const getStatusVariant = (status: ProductionOrderItem['status']) => {
        switch (status) {
            case 'pending':
                return 'secondary';
            case 'in_progress':
                return 'default';
            case 'pending_review':
                return 'outline';
            case 'completed':
                return 'default';
            case 'cancelled':
                return 'destructive';
            default:
                return 'secondary';
        }
    };

    const getStatusLabel = (status: string) =>
        statusOptions.find((o) => o.value === status)?.label ?? status;

    return (
        <>
            <Head title="Órdenes de Producción" />
            <div className="space-y-4 p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
                            Órdenes de Producción
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Gestión y seguimiento de la fabricación de pinturas
                            en Planta Cali.
                        </p>
                    </div>
                    {can.create && (
                        <Button asChild>
                            <Link href={productionOrderCreate().url}>
                                <Plus className="mr-2 h-4 w-4" />
                                Nueva Orden
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

                <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b border-border bg-muted/50">
                            <tr>
                                <th className="p-4 text-left font-medium">
                                    Orden #
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Producto
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Cant. Planificada
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Estado
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Fechas
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Fecha Fin
                                </th>
                                <th className="p-4 text-right font-medium">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {orders.data.map((order) => (
                                <tr
                                    key={order.id}
                                    className="border-b border-border/50 transition-colors hover:bg-muted/30"
                                >
                                    <td className="p-4">
                                        <div className="font-mono font-medium text-foreground">
                                            {order.order_number}
                                        </div>
                                        {order.lot_number ? (
                                            <div className="text-xs text-muted-foreground">
                                                #{order.lot_number}
                                            </div>
                                        ) : null}
                                    </td>
                                    <td className="p-4">
                                        <div className="font-medium text-foreground">
                                            {order.product?.name ?? 'S/N'}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            Fórmula v
                                            {order.formula?.version ?? 1}
                                        </div>
                                    </td>
                                    <td className="p-4 font-medium">
                                        {order.quantity} gal
                                    </td>
                                    <td className="p-4">
                                        <Badge
                                            variant={getStatusVariant(
                                                order.status,
                                            )}
                                        >
                                            {getStatusLabel(order.status)}
                                        </Badge>
                                    </td>
                                    <td className="p-4 text-xs">
                                        <div className="text-muted-foreground">
                                            Plan:{' '}
                                            <FormattedDate
                                                value={order.planned_date}
                                            />
                                        </div>
                                        <div className="text-muted-foreground/80">
                                            Creada:{' '}
                                            <FormattedDate
                                                value={order.created_at}
                                            />
                                        </div>
                                    </td>
                                    <td className="p-4 text-xs text-muted-foreground">
                                        {order.completion_date ? (
                                            <FormattedDate
                                                value={order.completion_date}
                                            />
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                    <td className="p-4 text-right">
                                        <TableActions
                                            actions={{
                                                view: true,
                                                edit: false,
                                                delete: false,
                                            }}
                                            onView={() =>
                                                router.get(
                                                    withReturnTo(
                                                        productionOrderShow({
                                                            production_order:
                                                                order.id,
                                                        }).url,
                                                    ),
                                                )
                                            }
                                        />
                                    </td>
                                </tr>
                            ))}
                            {orders.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="p-8 text-center text-sm text-muted-foreground"
                                    >
                                        No hay órdenes de producción
                                        registradas.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="mt-4 flex justify-center">
                    <Pagination links={orders.links} />
                </div>
            </div>
        </>
    );
}
