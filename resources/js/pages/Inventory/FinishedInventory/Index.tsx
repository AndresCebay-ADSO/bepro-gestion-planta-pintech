import { Head } from '@inertiajs/react';
import type { ComponentProps } from 'react';

import { DataListing } from '@/components/data-listing';
import type { DataListingColumn } from '@/components/data-listing';
import { DataTableFilters } from '@/components/data-table-filters';
import { EnumLabel } from '@/components/enum-label';
import { FormattedNumber } from '@/components/formatted-number';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import Pagination from '@/components/ui/pagination';
import { useFilters } from '@/hooks/use-filters';
import { withReturnTo } from '@/lib/navigation';
import { index as finishedInventoryIndex } from '@/routes/finished-inventory';
import { show as productsShow } from '@/routes/products';
import type { PaginationLink } from '@/types/ui';

type InventoryRow = {
    id: number;
    quantity: number;
    product: {
        id: number;
        name: string;
        category?: { name: string } | null;
    } | null;
    variant: {
        id: number;
        code: string | null;
        name: string;
        presentation_label: string | null;
        presentation_value: number | null;
    } | null;
    warehouse: {
        id: number;
        name: string;
        city: string;
        type: 'factory' | 'storage';
    } | null;
};

type Props = {
    inventory: {
        data: InventoryRow[];
        links: PaginationLink[];
    };
    warehouseOptions: { value: string; label: string }[];
    filters: {
        search?: string;
        warehouse_id?: number | string | null;
        product_id?: number | string | null;
        product_name?: string | null;
    };
    can: {
        viewProducts: boolean;
    };
};

export default function FinishedInventoryIndex({
    inventory,
    warehouseOptions,
    filters,
    can,
}: Props) {
    const {
        filters: filterState,
        setFilter,
        setFilterImmediate,
        clearFilters,
    } = useFilters({
        routeUrl: finishedInventoryIndex().url,
        initialFilters: {
            search: filters.search ?? '',
            warehouse_id: filters.warehouse_id
                ? String(filters.warehouse_id)
                : '',
            product_id: filters.product_id ? String(filters.product_id) : '',
        },
    });

    const filterFields: ComponentProps<typeof DataTableFilters>['fields'] = [
        {
            type: 'text',
            name: 'search',
            label: 'Buscar',
            placeholder: 'Buscar producto, variante o bodega...',
        },
        {
            type: 'select',
            name: 'warehouse_id',
            label: 'Bodega',
            options: warehouseOptions,
        },
    ];

    const clearProductFilter = () => {
        setFilterImmediate('product_id', '');
    };

    const columns: DataListingColumn<InventoryRow>[] = [
        {
            key: 'product',
            header: 'Producto',
            cell: (row) => row.product?.name ?? null,
            mobile: 'title',
        },
        {
            key: 'variant',
            header: 'Variante',
            cell: (row) =>
                row.variant ? (
                    <>
                        <div className="text-foreground">
                            {row.variant.name}
                        </div>
                        {(row.variant.presentation_label ||
                            row.variant.presentation_value != null) && (
                            <div className="text-xs">
                                {[
                                    row.variant.presentation_label,
                                    row.variant.presentation_value != null &&
                                        `(${row.variant.presentation_value})`,
                                ]
                                    .filter(Boolean)
                                    .join(' ')}
                            </div>
                        )}
                    </>
                ) : null,
            mobile: 'subtitle',
            className: 'text-muted-foreground',
        },
        {
            key: 'warehouse',
            header: 'Bodega',
            cell: (row) =>
                row.warehouse ? (
                    <>
                        <div className="text-foreground">
                            {row.warehouse.name}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            {row.warehouse.city} ·{' '}
                            <EnumLabel
                                enum="WarehouseType"
                                value={row.warehouse.type}
                            />
                        </div>
                    </>
                ) : null,
            mobile: 'meta',
        },
        {
            key: 'quantity',
            header: 'Cantidad',
            cell: (row) => (
                <FormattedNumber value={row.quantity} maxDecimals={2} />
            ),
            mobile: 'primary',
            align: 'right',
            className: 'font-medium text-foreground',
        },
    ];

    return (
        <>
            <Head title="Inventario PT" />

            <div className="space-y-6 p-6">
                <PageHeader
                    title="Inventario PT"
                    description="Disponibilidad de producto terminado por variante y bodega."
                />

                {filterState.product_id ? (
                    <div className="flex items-center gap-2 rounded-lg border border-border bg-muted/30 px-4 py-2 text-sm">
                        <span className="text-muted-foreground">
                            Filtrado por producto:{' '}
                            {filters.product_name ??
                                `#${filterState.product_id}`}
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={clearProductFilter}
                        >
                            Quitar filtro
                        </Button>
                    </div>
                ) : null}

                <DataTableFilters
                    fields={filterFields}
                    filters={filterState}
                    onFilter={setFilter}
                    onFilterImmediate={setFilterImmediate}
                    onClear={clearFilters}
                />

                <DataListing
                    rows={inventory.data}
                    rowKey={(row) => row.id}
                    // El detalle del producto pide otro permiso: sin él, el nombre queda como texto.
                    rowHref={(row) =>
                        row.product && can.viewProducts
                            ? withReturnTo(productsShow(row.product.id).url)
                            : undefined
                    }
                    columns={columns}
                    empty="No hay stock de producto terminado con los criterios seleccionados."
                />

                <div className="mt-4 flex justify-center">
                    <Pagination links={inventory.links} />
                </div>
            </div>
        </>
    );
}
