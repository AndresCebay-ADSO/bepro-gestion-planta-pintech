import { Head, Link, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';

import { ActiveBadge } from '@/components/active-badge';
import { DataListing } from '@/components/data-listing';
import type { DataListingColumn } from '@/components/data-listing';
import { DataTableFilters } from '@/components/data-table-filters';
import { FormattedNumber } from '@/components/formatted-number';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/hooks/use-confirm';
import { useFilters } from '@/hooks/use-filters';
import {
    create as categoriesCreate,
    destroy as categoriesDestroy,
    edit as categoriesEdit,
    index as categoriesIndex,
} from '@/routes/catalogs/raw-material-categories';
import type { RawMaterialCategoryRow } from '@/types';
import type { Paginated } from '@/types/ui';

type CategoryRow = RawMaterialCategoryRow & {
    can: {
        update: boolean;
        delete: boolean;
    };
};

type Props = {
    categories: Paginated<CategoryRow>;
    filters: Record<string, string | null | undefined>;
    typeOptions: { value: string; label: string }[];
    can: {
        create: boolean;
    };
};

export default function RawMaterialCategoriesIndex({
    categories,
    filters,
    typeOptions,
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
            type: filters.type ?? '',
        },
    });

    const confirm = useConfirm();

    const handleDelete = async (category: CategoryRow) => {
        if (
            !(await confirm({
                title: `¿Eliminar la categoría «${category.name}»?`,
                confirmLabel: 'Eliminar',
                destructive: true,
            }))
        ) {
            return;
        }

        router.delete(categoriesDestroy(category.id).url, {
            preserveScroll: true,
        });
    };

    const columns: DataListingColumn<CategoryRow>[] = [
        {
            key: 'code',
            header: 'Código',
            cell: (category) => category.code,
            mobile: 'title',
            className: 'font-mono',
            cardClassName: 'font-mono',
        },
        {
            key: 'name',
            header: 'Nombre',
            cell: (category) => category.name,
            mobile: 'subtitle',
        },
        {
            key: 'type',
            header: 'Tipo de insumo',
            cell: (category) => (
                <Badge variant="secondary">{category.type_label}</Badge>
            ),
            mobile: 'meta',
        },
        {
            key: 'raw_materials',
            header: 'Materias primas',
            cell: (category) => (
                <FormattedNumber value={category.raw_materials_count} />
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
            <Head title="Categorías de materia prima" />

            <div className="space-y-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <Heading
                        title="Categorías de materia prima"
                        description="Agrupan las materias primas. Su tipo de insumo decide dónde se ofrecen: fórmulas, envases, etiquetas o termoencogido."
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
                            placeholder: 'Buscar por código o nombre...',
                        },
                        {
                            type: 'select',
                            name: 'type',
                            label: 'Tipo de insumo',
                            options: typeOptions,
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

                {/* Sin detalle: el código abre la edición. */}
                <DataListing
                    rows={categories.data}
                    rowKey={(category) => category.id}
                    rowHref={(category) =>
                        category.can.update
                            ? categoriesEdit(category.id).url
                            : undefined
                    }
                    rowLabel={(category) => category.code}
                    columns={columns}
                    // El código ya abre la edición: el menú no la repite (DESIGN.md §9).
                    actions={(category) => [
                        {
                            label: 'Eliminar',
                            icon: Trash2,
                            destructive: true,
                            onSelect: () => handleDelete(category),
                            // Con materias primas no se puede eliminar (clave foránea RESTRICT): se desactiva.
                            // La salida («desactívala al editarla») solo se ofrece a quien puede editar.
                            disabledReason:
                                category.raw_materials_count > 0
                                    ? category.can.update
                                        ? 'No disponible: tiene materias primas. Desactívala al editarla.'
                                        : 'No disponible: tiene materias primas.'
                                    : undefined,
                            hidden: !category.can.delete,
                        },
                    ]}
                    empty="No se encontraron categorías."
                />

                <Pagination paginator={categories} />
            </div>
        </>
    );
}

RawMaterialCategoriesIndex.layout = {
    wide: true,
};
