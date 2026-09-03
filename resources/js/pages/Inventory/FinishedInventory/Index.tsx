import { Head, Link } from '@inertiajs/react';
import { Package } from 'lucide-react';
import type { ComponentProps } from 'react';

import { DataTableFilters } from '@/components/data-table-filters';
import { FormattedNumber } from '@/components/formatted-number';
import { Button } from '@/components/ui/button';
import Pagination from '@/components/ui/pagination';
import { useFilters } from '@/hooks/use-filters';
import { withReturnTo } from '@/lib/navigation';
import { index as finishedInventoryIndex } from '@/routes/finished-inventory';
import { show as productsShow } from '@/routes/products';
import type { PaginationLink } from '@/types/ui';

type InventoryRow = {
    id: number;
    quantity: string | number;
    product: {
        id: number;
        code: string;
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
};

function warehouseTypeLabel(type: 'factory' | 'storage'): string {
    return type === 'factory' ? 'Fábrica' : 'Almacenamiento';
}

export default function FinishedInventoryIndex({
    inventory,
    warehouseOptions,
    filters,
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

    return (
        <>
            <Head title="Inventario PT" />

            <div className="space-y-4 p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-semibold text-foreground">
                            <Package className="h-7 w-7 text-primary" />
                            Inventario PT
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Disponibilidad de producto terminado por variante y
                            bodega.
                        </p>
                    </div>
                </div>

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

                <div className="overflow-x-auto rounded-xl border border-border bg-card shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b border-border bg-muted/40">
                            <tr>
                                <th className="p-3 text-left font-medium">
                                    Producto
                                </th>
                                <th className="p-3 text-left font-medium">
                                    Variante
                                </th>
                                <th className="p-3 text-left font-medium">
                                    Bodega
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Cantidad
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {inventory.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="p-10 text-center text-muted-foreground"
                                    >
                                        No hay stock de producto terminado con
                                        los criterios seleccionados.
                                    </td>
                                </tr>
                            ) : (
                                inventory.data.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-b border-border/60 last:border-0"
                                    >
                                        <td className="p-3">
                                            {row.product ? (
                                                <Link
                                                    href={withReturnTo(
                                                        productsShow(
                                                            row.product.id,
                                                        ).url,
                                                    )}
                                                    className="font-medium text-foreground hover:text-primary"
                                                >
                                                    {row.product.name}
                                                </Link>
                                            ) : (
                                                '-'
                                            )}
                                            {row.product?.code ? (
                                                <div className="font-mono text-xs text-muted-foreground">
                                                    {row.product.code}
                                                </div>
                                            ) : null}
                                        </td>
                                        <td className="p-3 text-muted-foreground">
                                            {row.variant ? (
                                                <>
                                                    <div className="text-foreground">
                                                        {row.variant.name}
                                                    </div>
                                                    <div className="text-xs">
                                                        {row.variant
                                                            .presentation_label ??
                                                            '-'}{' '}
                                                        {row.variant
                                                            .presentation_value !=
                                                            null &&
                                                            `(${row.variant.presentation_value})`}
                                                    </div>
                                                </>
                                            ) : (
                                                '-'
                                            )}
                                        </td>
                                        <td className="p-3 text-muted-foreground">
                                            {row.warehouse ? (
                                                <>
                                                    <div className="text-foreground">
                                                        {row.warehouse.name}
                                                    </div>
                                                    <div className="text-xs">
                                                        {row.warehouse.city} ·{' '}
                                                        {warehouseTypeLabel(
                                                            row.warehouse.type,
                                                        )}
                                                    </div>
                                                </>
                                            ) : (
                                                '-'
                                            )}
                                        </td>
                                        <td className="p-3 text-right font-medium text-foreground">
                                            <FormattedNumber
                                                value={row.quantity}
                                                maxDecimals={2}
                                            />
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex justify-center">
                    <Pagination links={inventory.links} />
                </div>
            </div>
        </>
    );
}
