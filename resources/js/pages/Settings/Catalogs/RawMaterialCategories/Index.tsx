import { Head, Link, router } from '@inertiajs/react';
import { DataTableFilters } from '@/components/data-table-filters';
import Heading from '@/components/heading';
import { TableActions } from '@/components/table-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import Pagination from '@/components/ui/pagination';
import { useFilters } from '@/hooks/use-filters';
import {
    create as categoriesCreate,
    destroy as categoriesDestroy,
    edit as categoriesEdit,
    index as categoriesIndex,
} from '@/routes/catalogs/raw-material-categories';
import type { RawMaterialCategoryRow } from '@/types';
import type { PaginationLink } from '@/types/ui';

type CategoryRow = RawMaterialCategoryRow & {
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

    const handleDelete = (category: CategoryRow) => {
        if (!window.confirm(`¿Eliminar la categoría «${category.name}»?`)) {
            return;
        }

        router.delete(categoriesDestroy(category.id).url, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Categorías de materia prima" />

            <div className="space-y-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <Heading
                        variant="small"
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

                <div className="overflow-x-auto rounded-lg border border-border">
                    <table className="w-full text-sm">
                        <thead className="border-b border-border bg-muted/40">
                            <tr>
                                <th className="p-3 text-left font-medium text-foreground">
                                    Código
                                </th>
                                <th className="p-3 text-left font-medium text-foreground">
                                    Nombre
                                </th>
                                <th className="p-3 text-left font-medium text-foreground">
                                    Tipo de insumo
                                </th>
                                <th className="p-3 text-left font-medium text-foreground">
                                    Materias primas
                                </th>
                                <th className="p-3 text-left font-medium text-foreground">
                                    Estado
                                </th>
                                <th className="p-3 text-right font-medium text-foreground">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {categories.data.map((category) => {
                                const inUse = category.raw_materials_count > 0;

                                return (
                                    <tr
                                        key={category.id}
                                        className="border-b border-border/60 last:border-0"
                                    >
                                        <td className="p-3 font-mono text-foreground">
                                            {category.code}
                                        </td>
                                        <td className="p-3 text-foreground">
                                            {category.name}
                                        </td>
                                        <td className="p-3">
                                            <Badge variant="secondary">
                                                {category.type_label}
                                            </Badge>
                                        </td>
                                        <td className="p-3 text-muted-foreground">
                                            {category.raw_materials_count}
                                        </td>
                                        <td className="p-3">
                                            <span
                                                className={
                                                    category.is_active
                                                        ? 'rounded-full bg-emerald-500/15 px-2 py-1 text-xs font-medium text-emerald-600 dark:text-emerald-300'
                                                        : 'rounded-full bg-slate-500/15 px-2 py-1 text-xs font-medium text-slate-600 dark:text-slate-300'
                                                }
                                            >
                                                {category.is_active
                                                    ? 'Activa'
                                                    : 'Inactiva'}
                                            </span>
                                        </td>
                                        <td className="p-3 text-right">
                                            <TableActions
                                                permissions={{
                                                    view: false,
                                                    edit: category.can.update,
                                                    delete: category.can.delete,
                                                }}
                                                onEdit={() =>
                                                    router.get(
                                                        categoriesEdit(
                                                            category.id,
                                                        ).url,
                                                    )
                                                }
                                                onDelete={() =>
                                                    handleDelete(category)
                                                }
                                                // Con materias primas no se puede eliminar (clave foránea RESTRICT): se desactiva.
                                                disabled={{ delete: inUse }}
                                                tooltips={{
                                                    delete: inUse
                                                        ? 'Tiene materias primas: no se puede eliminar. Desactívala al editarla.'
                                                        : 'Eliminar categoría',
                                                }}
                                            />
                                        </td>
                                    </tr>
                                );
                            })}
                            {categories.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="p-8 text-center text-sm text-muted-foreground"
                                    >
                                        No se encontraron categorías.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex justify-center">
                    <Pagination links={categories.links} />
                </div>
            </div>
        </>
    );
}

RawMaterialCategoriesIndex.layout = {
    breadcrumbs: [
        { title: 'Categorías de materia prima', href: categoriesIndex() },
    ],
    wide: true,
};
