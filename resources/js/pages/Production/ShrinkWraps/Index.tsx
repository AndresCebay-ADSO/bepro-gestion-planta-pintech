import { Head, Link } from '@inertiajs/react';
import type { ComponentProps } from 'react';

import { DataListing } from '@/components/data-listing';
import type { DataListingColumn } from '@/components/data-listing';
import { DataTableFilters } from '@/components/data-table-filters';
import { FormattedDate } from '@/components/formatted-date';
import { FormattedNumber } from '@/components/formatted-number';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
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
            placeholder: 'Buscar por OP, lote, color o tipo...',
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

    const columns: DataListingColumn<Row>[] = [
        {
            key: 'wrapped_at',
            header: 'Fecha',
            cell: (row) => (
                <FormattedDate value={row.wrapped_at} format="date" />
            ),
            mobile: 'meta',
            className: 'whitespace-nowrap',
        },
        {
            key: 'order',
            header: 'Orden',
            cell: (row) =>
                `${row.order.order_number} · Lote ${row.order.lot_number}`,
            mobile: 'title',
            className: 'font-mono',
            cardClassName: 'font-mono',
        },
        {
            key: 'product',
            header: 'Producto',
            cell: (row) => row.order.product_name ?? '-',
            mobile: 'subtitle',
            className: 'text-muted-foreground',
        },
        {
            key: 'type',
            header: 'Tipo',
            cell: (row) => row.type_name,
            mobile: 'meta',
        },
        {
            key: 'applications',
            header: 'Aplicaciones',
            cell: (row) => <FormattedNumber value={row.applications} />,
            mobile: 'meta',
            align: 'right',
            className: 'font-medium',
        },
        {
            key: 'warehouse',
            header: 'Bodega',
            cell: (row) => row.warehouse_name,
            // Con el costo serían cinco datos: la bodega se ve en el detalle.
            mobile: can.viewCosts ? 'hidden' : 'meta',
            className: 'text-muted-foreground',
        },
        {
            key: 'created_by',
            header: 'Registró',
            cell: (row) => row.created_by_name,
            mobile: 'hidden',
            className: 'text-muted-foreground',
        },
        ...(can.viewCosts
            ? [
                  {
                      key: 'cost',
                      header: 'Costo',
                      cell: (row: Row) => (
                          <FormattedNumber
                              value={row.total_cost}
                              currency
                              maxDecimals={2}
                          />
                      ),
                      mobile: 'meta' as const,
                      align: 'right' as const,
                  },
              ]
            : []),
    ];

    return (
        <>
            <Head title="Termoencogido" />
            <div className="space-y-6 p-6">
                <PageHeader
                    title="Termoencogido"
                    description="Bandejas y bolsas gastadas al termoencoger producto de órdenes completadas. Su costo es gasto general."
                    actions={
                        can.create && (
                            <Button asChild>
                                <Link href={shrinkWrapsCreate().url}>
                                    Registrar termoencogido
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
                    rows={shrinkWraps.data}
                    rowKey={(row) => row.id}
                    rowHref={(row) =>
                        row.can.view ? shrinkWrapsShow(row.id).url : undefined
                    }
                    columns={columns}
                    empty="No hay termoencogidos registrados."
                />

                <div className="mt-4 flex justify-center">
                    <Pagination links={shrinkWraps.links} />
                </div>
            </div>
        </>
    );
}
