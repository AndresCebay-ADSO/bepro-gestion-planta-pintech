import { Head, Link, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';

import { ActiveBadge } from '@/components/active-badge';
import { DataListing } from '@/components/data-listing';
import type { DataListingColumn } from '@/components/data-listing';
import { DataTableFilters } from '@/components/data-table-filters';
import { FormattedNumber } from '@/components/formatted-number';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import Pagination from '@/components/ui/pagination';
import { useFilters } from '@/hooks/use-filters';
import {
    create as categoriesCreate,
    destroy as categoriesDestroy,
    edit as categoriesEdit,
    index as categoriesIndex,
} from '@/routes/catalogs/product-categories';
import type { ProductCategoryRow } from '@/types';
import type { PaginationLink } from '@/types/ui';

type CategoryRow = ProductCategoryRow & {
    can: {
        update: boolean;
        delete: boolean;
    };
};

type Props = {
    categories: {
        data: CategoryRow[];
        links: PaginationLink[];
    };
    filters: Record<string, string | null | undefined>;
    can: {
        create: boolean;
    };
};

export default function ProductCategoriesIndex({
    categories,
    filters,
    can,
}: Props) {
    const {
        filters: filterState,
        setFilter,
        clearFilters,
    } = useFilters({
        routeUrl: categoriesIndex().url,
        initialFilters: {
            search: filters.search ?? '',
            status: filters.status ?? '',
        },
    });

    const handleDelete = (category: CategoryRow) => {
        if (!window.confirm(`¿Eliminar la categoría «${category.name}»?`)) {
            return;
        }

        router.delete(categoriesDestroy(category.id).url, {
            preserveScroll: true,
        });
    };

    const columns: DataListingColumn<CategoryRow>[] = [
        {
            key: 'name',
            header: 'Nombre',
            cell: (category) => category.name,
            mobile: 'title',
        },
        {
            key: 'description',
            header: 'Descripción',
            cell: (category) => (
                <span
                    className="block md:max-w-xs md:truncate"
                    title={category.description ?? undefined}
                >
                    {category.description ?? '-'}
                </span>
            ),
            mobile: 'subtitle',
            className: 'text-muted-foreground',
        },
        {
            key: 'products',
            header: 'Productos',
            cell: (category) => (
                <FormattedNumber value={category.products_count} />
            ),
            mobile: 'meta',
            align: 'right',
            className: 'text-muted-foreground',
        },
        {
            key: 'status',
            header: 'Estado',
            cell: (category) => (
                <ActiveBadge isActive={category.is_active} feminine />
            ),
            mobile: 'status',
        },
    ];

    return (
        <>
            <Head title="Categorías de producto" />

            <div className="space-y-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <Heading
                        title="Categorías de producto"
                        description="Agrupan los productos terminados del portafolio."
                    />
                    {can.create && (
                        <Button asChild>
                            <Link href={categoriesCreate().url}>
                                Nueva categoría
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
                            placeholder: 'Buscar por nombre o descripción...',
                        },
                        {
                            type: 'select',
                            name: 'status',
                            label: 'Estado',
                            options: [
                                { value: 'active', label: 'Activas' },
                                { value: 'inactive', label: 'Inactivas' },
                            ],
                        },
                    ]}
                    filters={filterState}
                    onFilter={setFilter}
                    onClear={clearFilters}
                />

                {/* Sin detalle: el nombre abre la edición. */}
                <DataListing
                    rows={categories.data}
                    rowKey={(category) => category.id}
                    rowHref={(category) =>
                        category.can.update
                            ? categoriesEdit(category.id).url
                            : undefined
                    }
                    rowLabel={(category) => category.name}
                    columns={columns}
                    // El nombre ya abre la edición: el menú no la repite (DESIGN.md §9).
                    actions={(category) => [
                        {
                            label: 'Eliminar',
                            icon: Trash2,
                            destructive: true,
                            onSelect: () => handleDelete(category),
                            // Con productos no se puede eliminar (clave foránea RESTRICT): se desactiva.
                            disabledReason:
                                category.products_count > 0
                                    ? 'No disponible: tiene productos. Desactívala al editarla.'
                                    : undefined,
                            hidden: !category.can.delete,
                        },
                    ]}
                    empty="No se encontraron categorías."
                />

                <div className="flex justify-center">
                    <Pagination links={categories.links} />
                </div>
            </div>
        </>
    );
}

ProductCategoriesIndex.layout = {
    wide: true,
};
