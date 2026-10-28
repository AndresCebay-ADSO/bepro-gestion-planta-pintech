import { Head, Link } from '@inertiajs/react';

import { ActiveBadge } from '@/components/active-badge';
import { DataListing } from '@/components/data-listing';
import type { DataListingColumn } from '@/components/data-listing';
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

    const columns: DataListingColumn<ProductRow>[] = [
        {
            key: 'name',
            header: 'Nombre',
            cell: (product) => product.name,
            mobile: 'title',
        },
        {
            key: 'category',
            header: 'Categoría',
            cell: (product) => product.category?.name ?? null,
            mobile: 'subtitle',
        },
        {
            key: 'status',
            header: 'Estado',
            cell: (product) => <ActiveBadge isActive={product.is_active} />,
            mobile: 'status',
        },
    ];

    return (
        <>
            <Head title="Portafolio de productos" />

            <div className="space-y-6 p-6">
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

                <DataListing
                    rows={productsData.data}
                    rowKey={(product) => product.id}
                    rowHref={(product) =>
                        withReturnTo(productsShow(product.id).url)
                    }
                    columns={columns}
                    empty="No se encontraron productos."
                />

                <div className="mt-4 flex justify-center">
                    <Pagination links={productsData.links} />
                </div>
            </div>
        </>
    );
}
