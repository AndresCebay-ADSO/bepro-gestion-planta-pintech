import { Head } from '@inertiajs/react';
import type { ComponentProps } from 'react';

import { DataListing } from '@/components/data-listing';
import type { DataListingColumn } from '@/components/data-listing';
import { DataTableFilters } from '@/components/data-table-filters';
import { FormattedNumber } from '@/components/formatted-number';
import { PageHeader } from '@/components/page-header';
import Pagination from '@/components/ui/pagination';
import { useFilters } from '@/hooks/use-filters';
import { index as pricesIndex } from '@/routes/prices';
import type { PaginationLink } from '@/types/ui';

type VariantRow = {
    id: number;
    code: string | null;
    name: string;
    presentation_label: string | null;
    presentation_value: number | null;
    current_price?: number | null;
    sales_price: number | null;
    available_stock: number;
};

type ProductRow = {
    id: number;
    name: string;
    current_cost?: number | null;
    cif_percentage?: number | null;
    current_price?: number | null;
    sales_margin?: number | null;
    sales_price: number | null;
    variants: VariantRow[];
};

type Props = {
    products: {
        data: ProductRow[];
        links: PaginationLink[];
    };
    can: {
        view_costs: boolean;
        view_prices: boolean;
    };
    filters: {
        search?: string;
    };
};

export default function PricesIndex({
    products: productsData,
    can,
    filters,
}: Props) {
    const {
        filters: filterState,
        setFilter,
        setFilterImmediate,
        clearFilters,
    } = useFilters({
        routeUrl: pricesIndex().url,
        initialFilters: {
            search: filters.search ?? '',
        },
    });

    const filterFields: ComponentProps<typeof DataTableFilters>['fields'] = [
        {
            type: 'text',
            name: 'search',
            label: 'Buscar',
            placeholder: 'Buscar producto o presentación...',
        },
    ];

    const variantColumns: DataListingColumn<VariantRow>[] = [
        {
            key: 'presentation',
            header: 'Presentación',
            cell: (variant) => variant.presentation_label ?? variant.name,
            mobile: 'title',
        },
        {
            key: 'code',
            header: 'Código',
            cell: (variant) => variant.code,
            mobile: 'subtitle',
            className: 'font-mono text-muted-foreground',
            cardClassName: 'font-mono',
        },
        ...(can.view_costs
            ? [
                  {
                      // Costo con CIF: no es el «Costo» de la línea del producto.
                      key: 'internal_price',
                      header: 'Precio interno',
                      cell: (variant: VariantRow) => (
                          <FormattedNumber
                              value={variant.current_price}
                              currency
                              maxDecimals={2}
                          />
                      ),
                      mobile: 'meta' as const,
                      align: 'right' as const,
                      className: 'text-muted-foreground',
                  },
              ]
            : []),
        {
            key: 'sales_price',
            header: 'Precio de venta',
            // Sin precio es un dato que falta, no un vacío: se dice en vez de omitirlo.
            cell: (variant) =>
                variant.sales_price === null ? (
                    <span className="text-muted-foreground">Sin precio</span>
                ) : (
                    <FormattedNumber
                        value={variant.sales_price}
                        currency
                        maxDecimals={2}
                    />
                ),
            mobile: 'primary',
            align: 'right',
            className: 'font-medium',
        },
        {
            key: 'stock',
            header: 'Stock disponible',
            cell: (variant) => (
                <FormattedNumber
                    value={variant.available_stock}
                    maxDecimals={2}
                />
            ),
            mobile: 'meta',
            align: 'right',
        },
    ];

    return (
        <>
            <Head title="Lista de precios" />

            <div className="space-y-6 p-6">
                <PageHeader
                    title="Lista de precios"
                    description="Precios de venta vigentes por producto y presentación."
                />

                <DataTableFilters
                    fields={filterFields}
                    filters={filterState}
                    onFilter={setFilter}
                    onFilterImmediate={setFilterImmediate}
                    onClear={clearFilters}
                />

                {productsData.data.length === 0 ? (
                    <div className="rounded-lg border border-dashed border-border px-4 py-12 text-center text-sm text-muted-foreground">
                        No hay productos registrados.
                    </div>
                ) : (
                    productsData.data.map((product) => (
                        <section key={product.id} className="space-y-3">
                            <div className="space-y-1">
                                <h2 className="text-lg font-semibold">
                                    {product.name}
                                </h2>
                                {/* En el celular, Costo y Margen pasan a dos líneas. */}
                                {can.view_costs && (
                                    <p className="flex flex-wrap gap-x-2 text-sm text-muted-foreground">
                                        <span className="whitespace-nowrap">
                                            Costo:{' '}
                                            <FormattedNumber
                                                value={product.current_cost}
                                                currency
                                                maxDecimals={2}
                                            />
                                        </span>
                                        <span
                                            aria-hidden="true"
                                            className="hidden sm:inline"
                                        >
                                            ·
                                        </span>
                                        <span className="whitespace-nowrap">
                                            Margen de venta:{' '}
                                            <FormattedNumber
                                                value={product.sales_margin}
                                                maxDecimals={2}
                                            />
                                            % (s/ venta)
                                        </span>
                                    </p>
                                )}
                            </div>

                            <DataListing
                                rows={product.variants}
                                rowKey={(variant) => variant.id}
                                columns={variantColumns}
                                empty="No hay presentaciones registradas."
                            />
                        </section>
                    ))
                )}

                <div className="flex justify-center">
                    <Pagination links={productsData.links} />
                </div>
            </div>
        </>
    );
}
