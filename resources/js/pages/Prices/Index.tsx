import { Head } from '@inertiajs/react';
import type { ComponentProps } from 'react';

import { DataTableFilters } from '@/components/data-table-filters';
import { FormattedNumber } from '@/components/formatted-number';
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
    current_price: number | null;
    sales_price: number | null;
    available_stock: number;
};

type ProductRow = {
    id: number;
    code: string | null;
    name: string;
    current_cost: number | null;
    cif_percentage: number | null;
    current_price: number | null;
    sales_margin: number | null;
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

    return (
        <>
            <Head title="Lista de Precios" />

            <div className="space-y-4 p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
                            Lista de Precios
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Precios de venta vigentes por producto y
                            presentación.
                        </p>
                    </div>
                </div>

                <DataTableFilters
                    fields={filterFields}
                    filters={filterState}
                    onFilter={setFilter}
                    onFilterImmediate={setFilterImmediate}
                    onClear={clearFilters}
                />

                <div className="space-y-6">
                    {productsData.data.length === 0 ? (
                        <div className="rounded-xl border border-border bg-card p-8 text-center text-sm text-muted-foreground">
                            No hay productos registrados.
                        </div>
                    ) : (
                        productsData.data.map((product) => (
                            <div
                                key={product.id}
                                className="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
                            >
                                <div className="border-b border-border bg-muted/30 p-4">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <h3 className="font-semibold text-foreground">
                                                {product.name}
                                            </h3>
                                            {product.code && (
                                                <span className="font-mono text-xs text-muted-foreground">
                                                    {product.code}
                                                </span>
                                            )}
                                        </div>
                                        {can.view_costs && (
                                            <div className="flex items-center gap-4 text-sm text-muted-foreground">
                                                <span>
                                                    Costo:{' '}
                                                    <FormattedNumber
                                                        value={
                                                            product.current_cost
                                                        }
                                                        currency
                                                        maxDecimals={2}
                                                    />
                                                </span>
                                                <span>
                                                    Margen Venta:{' '}
                                                    <FormattedNumber
                                                        value={
                                                            product.sales_margin
                                                        }
                                                        maxDecimals={2}
                                                    />
                                                    % (s/ venta)
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="border-b border-border bg-muted/50">
                                            <tr>
                                                <th className="p-4 text-left font-medium">
                                                    Variante
                                                </th>
                                                <th className="p-4 text-left font-medium">
                                                    Presentación
                                                </th>
                                                {can.view_costs && (
                                                    <>
                                                        <th className="p-4 text-right font-medium">
                                                            Precio Interno
                                                        </th>
                                                    </>
                                                )}
                                                <th className="p-4 text-right font-medium">
                                                    Precio Venta
                                                </th>
                                                <th className="p-4 text-right font-medium">
                                                    Disponible
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {product.variants.length === 0 ? (
                                                <tr>
                                                    <td
                                                        colSpan={
                                                            can.view_costs
                                                                ? 5
                                                                : 4
                                                        }
                                                        className="p-4 text-center text-sm text-muted-foreground"
                                                    >
                                                        No hay variantes
                                                        registradas.
                                                    </td>
                                                </tr>
                                            ) : (
                                                product.variants.map(
                                                    (variant) => (
                                                        <tr
                                                            key={variant.id}
                                                            className="border-b border-border/50 transition-colors hover:bg-muted/30"
                                                        >
                                                            <td className="p-4">
                                                                <div className="font-medium text-foreground">
                                                                    {
                                                                        variant.name
                                                                    }
                                                                </div>
                                                                {variant.code && (
                                                                    <div className="font-mono text-xs text-muted-foreground">
                                                                        {
                                                                            variant.code
                                                                        }
                                                                    </div>
                                                                )}
                                                            </td>
                                                            <td className="p-4">
                                                                <span className="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 dark:bg-blue-900/20 dark:text-blue-300">
                                                                    {variant.presentation_label ??
                                                                        '-'}{' '}
                                                                    {variant.presentation_value !==
                                                                        null && (
                                                                        <span className="ml-1 text-muted-foreground">
                                                                            (
                                                                            {
                                                                                variant.presentation_value
                                                                            }{' '}
                                                                            gl)
                                                                        </span>
                                                                    )}
                                                                </span>
                                                            </td>
                                                            {can.view_costs && (
                                                                <td className="p-4 text-right text-muted-foreground">
                                                                    <FormattedNumber
                                                                        value={
                                                                            variant.current_price
                                                                        }
                                                                        currency
                                                                        maxDecimals={
                                                                            2
                                                                        }
                                                                    />
                                                                </td>
                                                            )}
                                                            <td className="p-4 text-right">
                                                                <FormattedNumber
                                                                    value={
                                                                        variant.sales_price
                                                                    }
                                                                    currency
                                                                    maxDecimals={
                                                                        2
                                                                    }
                                                                    bold
                                                                    size="lg"
                                                                />
                                                            </td>
                                                            <td className="p-4 text-right text-muted-foreground">
                                                                <FormattedNumber
                                                                    value={
                                                                        variant.available_stock
                                                                    }
                                                                    maxDecimals={
                                                                        2
                                                                    }
                                                                />
                                                            </td>
                                                        </tr>
                                                    ),
                                                )
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        ))
                    )}
                </div>

                <div className="flex justify-center">
                    <Pagination links={productsData.links} />
                </div>
            </div>
        </>
    );
}
