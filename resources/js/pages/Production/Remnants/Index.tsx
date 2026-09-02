import { Head, Link } from '@inertiajs/react';
import type { ComponentProps } from 'react';

import { DataTableFilters } from '@/components/data-table-filters';
import { FormattedNumber } from '@/components/formatted-number';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
    product_code: string | null;
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
};

export default function RemnantsIndex({
    remnants,
    filters,
    statusOptions,
    warehouseOptions,
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
            placeholder: 'Buscar por producto u orden...',
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

    const getStatusVariant = (
        status: string,
    ): 'default' | 'secondary' | 'outline' | 'destructive' => {
        switch (status) {
            case 'available':
                return 'default';
            case 'partially_consumed':
                return 'secondary';
            case 'consumed':
                return 'outline';
            default:
                return 'secondary';
        }
    };

    return (
        <>
            <Head title="Saldos de Producción" />
            <div className="space-y-4 p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-foreground">
                        Saldos de Producción
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Producto terminado sobrante de órdenes completadas,
                        disponible para reutilizar en nuevas mezclas.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Saldos de Producto Terminado</CardTitle>
                        <CardDescription>
                            {remnants.total} saldo
                            {remnants.total !== 1 ? 's' : ''} registrado
                            {remnants.total !== 1 ? 's' : ''}
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
                                                Producto
                                            </th>
                                            <th className="p-4 text-left font-medium">
                                                Orden Origen
                                            </th>
                                            <th className="p-4 text-left font-medium">
                                                Bodega
                                            </th>
                                            <th className="p-4 text-right font-medium">
                                                Densidad
                                            </th>
                                            <th className="p-4 text-right font-medium">
                                                Costo/gal
                                            </th>
                                            <th className="p-4 text-right font-medium">
                                                Disponible (gal)
                                            </th>
                                            <th className="p-4 text-right font-medium">
                                                Disponible (kg)
                                            </th>
                                            <th className="p-4 text-left font-medium">
                                                Estado
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {remnants.data.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={8}
                                                    className="p-8 text-center text-sm text-muted-foreground"
                                                >
                                                    No hay saldos registrados.
                                                </td>
                                            </tr>
                                        ) : (
                                            remnants.data.map((remnant) => (
                                                <tr
                                                    key={remnant.id}
                                                    className="border-b border-border/50 transition-colors hover:bg-muted/30"
                                                >
                                                    <td className="p-4">
                                                        <div className="font-medium text-foreground">
                                                            {
                                                                remnant.product_name
                                                            }
                                                        </div>
                                                        {remnant.product_code && (
                                                            <div className="text-xs text-muted-foreground">
                                                                {
                                                                    remnant.product_code
                                                                }
                                                            </div>
                                                        )}
                                                    </td>
                                                    <td className="p-4">
                                                        <Link
                                                            href={
                                                                productionOrderShow(
                                                                    {
                                                                        production_order:
                                                                            remnant.source_order_id,
                                                                    },
                                                                ).url
                                                            }
                                                            className="font-medium text-primary hover:underline"
                                                        >
                                                            {
                                                                remnant.source_order_number
                                                            }
                                                        </Link>
                                                    </td>
                                                    <td className="p-4 text-muted-foreground">
                                                        {remnant.warehouse_name}
                                                    </td>
                                                    <td className="p-4 text-right">
                                                        <FormattedNumber
                                                            value={
                                                                remnant.density_kg_per_gallon
                                                            }
                                                            maxDecimals={4}
                                                        />{' '}
                                                        <span className="text-xs text-muted-foreground">
                                                            kg/gal
                                                        </span>
                                                    </td>
                                                    <td className="p-4 text-right">
                                                        {remnant.cost_per_gallon !=
                                                        null ? (
                                                            <FormattedNumber
                                                                value={
                                                                    remnant.cost_per_gallon
                                                                }
                                                                currency
                                                                maxDecimals={2}
                                                            />
                                                        ) : (
                                                            <span className="text-muted-foreground">
                                                                ---
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="p-4 text-right font-medium">
                                                        <FormattedNumber
                                                            value={
                                                                remnant.available_quantity_gallons
                                                            }
                                                            maxDecimals={4}
                                                        />
                                                    </td>
                                                    <td className="p-4 text-right text-muted-foreground">
                                                        <FormattedNumber
                                                            value={
                                                                remnant.available_quantity_kg
                                                            }
                                                            maxDecimals={4}
                                                        />
                                                    </td>
                                                    <td className="p-4">
                                                        <Badge
                                                            variant={getStatusVariant(
                                                                remnant.status,
                                                            )}
                                                        >
                                                            {
                                                                remnant.status_label
                                                            }
                                                        </Badge>
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div className="flex justify-center">
                            <Pagination links={remnants.links} />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
