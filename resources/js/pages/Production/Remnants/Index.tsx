import { Head } from '@inertiajs/react';
import type { ComponentProps } from 'react';

import { DataListing } from '@/components/data-listing';
import type { DataListingColumn } from '@/components/data-listing';
import { DataTableFilters } from '@/components/data-table-filters';
import { FormattedNumber } from '@/components/formatted-number';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import Pagination from '@/components/ui/pagination';
import { useFilters } from '@/hooks/use-filters';
import { index as remnantsIndex } from '@/routes/production/remnants';
import { show as productionOrderShow } from '@/routes/production-orders';
import type { PaginationLink } from '@/types/ui';

type RemnantItem = {
    id: number;
    source_order_id: number;
    source_order_number: string;
    product_id: number;
    product_name: string;
    warehouse_id: number;
    warehouse_name: string;
    available_quantity_gallons: number;
    available_quantity_kg: number;
    density_kg_per_gallon: number;
    cost_per_gallon: number | null;
    status: string;
    status_label: string;
    created_at: string;
};

type Option = {
    value: string;
    label: string;
};

type Props = {
    remnants: {
        data: RemnantItem[];
        links: PaginationLink[];
        total: number;
    };
    filters: Record<string, string | null | undefined>;
    statusOptions: Option[];
    warehouseOptions: Option[];
    can: {
        viewCosts: boolean;
        viewProductionOrders: boolean;
    };
};

export default function RemnantsIndex({
    remnants,
    filters,
    statusOptions,
    warehouseOptions,
    can,
}: Props) {
    const {
        filters: filterState,
        setFilter,
        setFilterImmediate,
        clearFilters,
    } = useFilters({
        routeUrl: remnantsIndex().url,
        initialFilters: {
            search: filters.search ?? '',
            status: filters.status ?? '',
            warehouse_id: filters.warehouse_id ?? '',
        },
    });

    const filterFields: ComponentProps<typeof DataTableFilters>['fields'] = [
        {
            type: 'text',
            name: 'search',
            label: 'Buscar',
            placeholder: 'Buscar por producto, color u orden...',
        },
        {
            type: 'select',
            name: 'status',
            label: 'Estado',
            options: statusOptions,
        },
        {
            type: 'select',
            name: 'warehouse_id',
            label: 'Bodega',
            options: warehouseOptions,
        },
    ];

    const columns: DataListingColumn<RemnantItem>[] = [
        {
            key: 'product',
            header: 'Producto',
            cell: (remnant) => remnant.product_name,
            mobile: 'subtitle',
            className: 'font-medium text-foreground',
        },
        {
            key: 'source_order',
            header: 'Orden origen',
            cell: (remnant) => remnant.source_order_number,
            mobile: 'title',
            className: 'font-mono',
            cardClassName: 'font-mono',
        },
        {
            key: 'warehouse',
            header: 'Bodega',
            cell: (remnant) => remnant.warehouse_name,
            mobile: 'meta',
            className: 'text-muted-foreground',
        },
        {
            key: 'density',
            header: 'Densidad',
            cell: (remnant) => (
                <>
                    <FormattedNumber
                        value={remnant.density_kg_per_gallon}
                        maxDecimals={4}
                    />{' '}
                    <span className="text-xs text-muted-foreground">
                        kg/gal
                    </span>
                </>
            ),
            // El principal no cuenta en los 4 datos: la densidad cabe en la tarjeta, también con el costo.
            mobile: 'meta',
            align: 'right',
        },
        ...(can.viewCosts
            ? [
                  {
                      key: 'cost',
                      header: 'Costo/gal',
                      // Sin costo es un dato que falta, no un vacío: se dice (como «Sin precio» en Lista de precios).
                      cell: (remnant: RemnantItem) =>
                          remnant.cost_per_gallon != null ? (
                              <FormattedNumber
                                  value={remnant.cost_per_gallon}
                                  currency
                                  maxDecimals={2}
                              />
                          ) : (
                              <span className="text-muted-foreground">
                                  Sin costo
                              </span>
                          ),
                      mobile: 'meta' as const,
                      align: 'right' as const,
                  },
              ]
            : []),
        {
            key: 'available_gallons',
            header: 'Disponible (gal)',
            cell: (remnant) => (
                <FormattedNumber
                    value={remnant.available_quantity_gallons}
                    maxDecimals={4}
                />
            ),
            mobile: 'primary',
            align: 'right',
            className: 'font-medium',
        },
        {
            key: 'available_kg',
            header: 'Disponible (kg)',
            cell: (remnant) => (
                <FormattedNumber
                    value={remnant.available_quantity_kg}
                    maxDecimals={4}
                />
            ),
            mobile: 'meta',
            align: 'right',
            className: 'text-muted-foreground',
        },
        {
            key: 'status',
            header: 'Estado',
            cell: (remnant) => (
                <StatusBadge enum="RemnantStatus" value={remnant.status} />
            ),
            mobile: 'status',
        },
    ];

    return (
        <>
            <Head title="Saldos de producción" />
            <div className="space-y-6 p-6">
                <PageHeader
                    title="Saldos de producción"
                    description="Producto terminado sobrante de órdenes completadas, disponible para reutilizar en nuevas mezclas."
                />

                <DataTableFilters
                    fields={filterFields}
                    filters={filterState}
                    onFilter={setFilter}
                    onFilterImmediate={setFilterImmediate}
                    onClear={clearFilters}
                />

                <DataListing
                    rows={remnants.data}
                    rowKey={(remnant) => remnant.id}
                    // Los saldos no tienen detalle: la orden de origen abre su OP, si se tiene permiso de verla.
                    rowHref={(remnant) =>
                        can.viewProductionOrders
                            ? productionOrderShow({
                                  production_order: remnant.source_order_id,
                              }).url
                            : undefined
                    }
                    columns={columns}
                    empty="No hay saldos registrados."
                />

                <div className="mt-4 flex justify-center">
                    <Pagination links={remnants.links} />
                </div>
            </div>
        </>
    );
}
