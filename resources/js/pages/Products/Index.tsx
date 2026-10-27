import { Head, Link } from '@inertiajs/react';

import { ActiveBadge } from '@/components/active-badge';
import { DataTableFilters } from '@/components/data-table-filters';
import { PageHeader } from '@/components/page-header';
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
            status: filters.status ?? '',
        },
    });

    return (
        <>
            <Head title="Portafolio de productos" />

            <div className="space-y-4 p-6">
                <PageHeader
                    title="Portafolio de productos"
                    description="Gestión del catálogo de productos y precios."
                    actions={
                        can.create && (
                            <Button asChild>
                                <Link href={productsCreate().url}>
                                    Nuevo producto
                                </Link>
                            </Button>
                        )
                    }
                />

                <DataTableFilters
                    fields={[
                        {
                            type: 'text',
                            name: 'search',
                            label: 'Buscar',
                            placeholder: 'Buscar por nombre o código...',
                        },
                        {
                            type: 'select',
                            name: 'status',
                            label: 'Estado',
                            options: [
                                { value: 'active', label: 'Activos' },
                                { value: 'inactive', label: 'Inactivos' },
                            ],
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
                                    <td className="p-3">
                                        {product.category?.name ?? '-'}
                                    </td>
                                    <td className="p-3">
                                        <ActiveBadge
                                            isActive={product.is_active}
                                        />
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
