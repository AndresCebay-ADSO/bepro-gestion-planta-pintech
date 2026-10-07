import { Head, Link, router } from '@inertiajs/react';
import type { ComponentProps } from 'react';

import { DataTableFilters } from '@/components/data-table-filters';
import { FormattedDate } from '@/components/formatted-date';
import { FormattedNumber } from '@/components/formatted-number';
import { TableActions } from '@/components/table-actions';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import Pagination from '@/components/ui/pagination';
import { useFilters } from '@/hooks/use-filters';
import {
    create as shrinkWrapsCreate,
    index as shrinkWrapsIndex,
    show as shrinkWrapsShow,
} from '@/routes/production/shrink-wraps';
import type { ShrinkWrapRow } from '@/types';
import type { PaginationLink } from '@/types/ui';

type Row = ShrinkWrapRow & { can: { view: boolean } };

type Props = {
    shrinkWraps: {
        data: Row[];
        links: PaginationLink[];
        total: number;
    };
    filters: Record<string, string | null | undefined>;
    typeOptions: { value: string; label: string }[];
    can: {
        create: boolean;
        viewCosts: boolean;
    };
};

export default function ShrinkWrapsIndex({
    shrinkWraps,
    filters,
    typeOptions,
    can,
}: Props) {
    const {
        filters: filterState,
        setFilter,
        setFilterImmediate,
        clearFilters,
    } = useFilters({
        routeUrl: shrinkWrapsIndex().url,
        initialFilters: {
            search: filters.search ?? '',
            shrink_wrap_type_id: filters.shrink_wrap_type_id ?? '',
            date_from: filters.date_from ?? '',
            date_to: filters.date_to ?? '',
        },
    });

    const filterFields: ComponentProps<typeof DataTableFilters>['fields'] = [
        {
            type: 'text',
            name: 'search',
            label: 'Buscar',
            placeholder: 'Buscar por OP, lote o tipo...',
        },
        {
            type: 'select',
            name: 'shrink_wrap_type_id',
            label: 'Tipo',
            options: typeOptions,
        },
        {
            type: 'date-range',
            nameFrom: 'date_from',
            nameTo: 'date_to',
            label: 'Fecha',
        },
    ];

    const columns = can.viewCosts ? 8 : 7;

    return (
        <>
            <Head title="Termoencogido" />
            <div className="space-y-4 p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
                            Termoencogido
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Bandejas y bolsas gastadas al termoencoger producto
                            de órdenes completadas. Su costo es gasto general.
                        </p>
                    </div>
                    {can.create && (
                        <Button asChild>
                            <Link href={shrinkWrapsCreate().url}>
                                Registrar termoencogido
                            </Link>
                        </Button>
                    )}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Registros</CardTitle>
                        <CardDescription>
                            {shrinkWraps.total} registro
                            {shrinkWraps.total !== 1 ? 's' : ''}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <DataTableFilters
                            fields={filterFields}
                            filters={filterState}
                            onFilter={setFilter}
                            onFilterImmediate={setFilterImmediate}
                            onClear={clearFilters}
                        />

                        <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b border-border bg-muted/50">
                                        <tr>
                                            <th className="p-4 text-left font-medium">
                                                Fecha
                                            </th>
                                            <th className="p-4 text-left font-medium">
                                                Orden
                                            </th>
                                            <th className="p-4 text-left font-medium">
                                                Tipo
                                            </th>
                                            <th className="p-4 text-right font-medium">
                                                Aplicaciones
                                            </th>
                                            <th className="p-4 text-left font-medium">
                                                Bodega
                                            </th>
                                            <th className="p-4 text-left font-medium">
                                                Registró
                                            </th>
                                            {can.viewCosts && (
                                                <th className="p-4 text-right font-medium">
                                                    Costo
                                                </th>
                                            )}
                                            <th className="p-4 text-right font-medium">
                                                Acciones
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {shrinkWraps.data.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={columns}
                                                    className="p-8 text-center text-sm text-muted-foreground"
                                                >
                                                    No hay termoencogidos
                                                    registrados.
                                                </td>
                                            </tr>
                                        ) : (
                                            shrinkWraps.data.map((row) => (
                                                <tr
                                                    key={row.id}
                                                    className="border-b border-border/50 transition-colors hover:bg-muted/30"
                                                >
                                                    <td className="p-4 whitespace-nowrap">
                                                        <FormattedDate
                                                            value={
                                                                row.wrapped_at
                                                            }
                                                            format="date"
                                                        />
                                                    </td>
                                                    <td className="p-4">
                                                        <div className="font-medium text-foreground">
                                                            {
                                                                row.order
                                                                    .order_number
                                                            }
                                                            {` · Lote ${row.order.lot_number}`}
                                                        </div>
                                                        {row.order
                                                            .product_name && (
                                                            <div className="text-xs text-muted-foreground">
                                                                {
                                                                    row.order
                                                                        .product_name
                                                                }
                                                            </div>
                                                        )}
                                                    </td>
                                                    <td className="p-4">
                                                        {row.type_name}
                                                    </td>
                                                    <td className="p-4 text-right font-medium">
                                                        {row.applications}
                                                    </td>
                                                    <td className="p-4 text-muted-foreground">
                                                        {row.warehouse_name}
                                                    </td>
                                                    <td className="p-4 text-muted-foreground">
                                                        {row.created_by_name}
                                                    </td>
                                                    {can.viewCosts && (
                                                        <td className="p-4 text-right">
                                                            <FormattedNumber
                                                                value={
                                                                    row.total_cost
                                                                }
                                                                currency
                                                                maxDecimals={2}
                                                            />
                                                        </td>
                                                    )}
                                                    <td className="p-4 text-right">
                                                        <TableActions
                                                            permissions={{
                                                                view: row.can
                                                                    .view,
                                                                edit: false,
                                                                delete: false,
                                                            }}
                                                            onView={() =>
                                                                router.get(
                                                                    shrinkWrapsShow(
                                                                        row.id,
                                                                    ).url,
                                                                )
                                                            }
                                                            tooltips={{
                                                                view: 'Ver termoencogido',
                                                            }}
                                                        />
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div className="flex justify-center">
                            <Pagination links={shrinkWraps.links} />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
