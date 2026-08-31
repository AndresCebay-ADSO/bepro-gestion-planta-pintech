import { Head, Link } from '@inertiajs/react';

import { DataTableFilters } from '@/components/data-table-filters';
import { Button } from '@/components/ui/button';
import Pagination from '@/components/ui/pagination';
import { useFilters } from '@/hooks/use-filters';
import { withReturnTo } from '@/lib/navigation';
import {
    create as productsCreate,
    index as productsIndex,
    show as productsShow,
} from '@/routes/products';
import type { PaginationLink } from '@/types/ui';

type ProductRow = {
    id: number;
    code: string;
    name: string;
    category?: { id: number; name: string } | null;
    is_active: boolean;
};

type Props = {
    products: {
        data: ProductRow[];
        links: PaginationLink[];
    };
    can: {
        create: boolean;
    };
    filters: Record<string, string | null | undefined>;
};

export default function ProductsIndex({
    products: productsData,
    can,
    filters,
}: Props) {
    const {
        filters: filterState,
        setFilter,
        clearFilters,
    } = useFilters({
        routeUrl: productsIndex().url,
        initialFilters: {
            search: filters.search ?? '',
        },
    });

    return (
        <>
            <Head title="Productos" />

            <div className="space-y-4 p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
                            Productos
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Gestión del catálogo de productos y precios.
                        </p>
                    </div>
                    {can.create && (
                        <Button asChild>
                            <Link href={productsCreate().url}>
                                Nuevo Producto
                            </Link>
                        </Button>
                    )}
                </div>

                <DataTableFilters
                    fields={[
                        {
                            type: 'text',
                            name: 'search',
                            label: 'Buscar',
                            placeholder: 'Buscar por nombre o código...',
                        },
                    ]}
                    filters={filterState}
                    onFilter={setFilter}
                    onClear={clearFilters}
                />

                <div className="rounded border border-border bg-card">
                    <table className="w-full text-sm">
                        <thead className="border-b border-border">
                            <tr>
                                <th className="p-3 text-left">Nombre</th>
                                <th className="p-3 text-left">Referencia</th>
                                <th className="p-3 text-left">Categoría</th>
                                <th className="p-3 text-left">Estado</th>
                                <th className="p-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {productsData.data.map((product) => (
                                <tr
                                    key={product.id}
                                    className="border-b border-border/50"
                                >
                                    <td className="p-3 font-medium">
                                        {product.name}
                                    </td>
                                    <td className="p-3 font-mono text-muted-foreground">
                                        {product.code || '-'}
                                    </td>
                                    <td className="p-3">
                                        {product.category?.name ?? '-'}
                                    </td>
                                    <td className="p-3">
                                        <span
                                            className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${product.is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'}`}
                                        >
                                            {product.is_active
                                                ? 'Activo'
                                                : 'Inactivo'}
                                        </span>
                                    </td>
                                    <td className="space-x-2 p-3 text-right">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={withReturnTo(
                                                    productsShow(product.id)
                                                        .url,
                                                )}
                                            >
                                                Ver
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="mt-4 flex justify-center">
                    <Pagination links={productsData.links} />
                </div>
            </div>
        </>
    );
}
