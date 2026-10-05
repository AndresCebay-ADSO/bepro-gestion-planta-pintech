import { Head, Link, router } from '@inertiajs/react';
import { DataTableFilters } from '@/components/data-table-filters';
import { FormattedNumber } from '@/components/formatted-number';
import Heading from '@/components/heading';
import { TableActions } from '@/components/table-actions';
import { Button } from '@/components/ui/button';
import Pagination from '@/components/ui/pagination';
import { useFilters } from '@/hooks/use-filters';
import {
    create as typesCreate,
    destroy as typesDestroy,
    edit as typesEdit,
    index as typesIndex,
} from '@/routes/catalogs/shrink-wrap-types';
import type { ShrinkWrapTypeRow } from '@/types';
import type { PaginationLink } from '@/types/ui';

type TypeRow = ShrinkWrapTypeRow & {
    can: {
        update: boolean;
        delete: boolean;
    };
};

type Props = {
    types: {
        data: TypeRow[];
        links: PaginationLink[];
    };
    filters: Record<string, string | null | undefined>;
    can: {
        create: boolean;
    };
};

export default function ShrinkWrapTypesIndex({ types, filters, can }: Props) {
    const {
        filters: filterState,
        setFilter,
        clearFilters,
    } = useFilters({
        routeUrl: typesIndex().url,
        initialFilters: {
            search: filters.search ?? '',
            status: filters.status ?? '',
        },
    });

    const handleDelete = (type: TypeRow) => {
        if (!window.confirm(`¿Eliminar el tipo «${type.name}»?`)) {
            return;
        }

        router.delete(typesDestroy(type.id).url, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Tipos de termoencogido" />

            <div className="space-y-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <Heading
                        variant="small"
                        title="Tipos de termoencogido"
                        description="Cada tipo dice cuánto empaque secundario se gasta por aplicación. El operario lo elige al registrar un termoencogido."
                    />
                    {can.create && (
                        <Button asChild>
                            <Link href={typesCreate().url}>Nuevo tipo</Link>
                        </Button>
                    )}
                </div>

                <DataTableFilters
                    fields={[
                        {
                            type: 'text',
                            name: 'search',
                            label: 'Buscar',
                            placeholder: 'Buscar por nombre...',
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

                <div className="overflow-x-auto rounded-lg border border-border">
                    <table className="w-full text-sm">
                        <thead className="border-b border-border bg-muted/40">
                            <tr>
                                <th className="p-3 text-left font-medium text-foreground">
                                    Nombre
                                </th>
                                <th className="p-3 text-left font-medium text-foreground">
                                    Consumo por aplicación
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
                            {types.data.map((type) => (
                                <tr
                                    key={type.id}
                                    className="border-b border-border/60 last:border-0"
                                >
                                    <td className="p-3 text-foreground">
                                        {type.name}
                                    </td>
                                    <td className="p-3 text-muted-foreground">
                                        <ul className="space-y-0.5">
                                            {type.items.map((item) => (
                                                <li key={item.raw_material_id}>
                                                    <FormattedNumber
                                                        value={item.quantity}
                                                        maxDecimals={4}
                                                    />{' '}
                                                    {item.unit_symbol}{' '}
                                                    <span className="font-mono text-foreground">
                                                        {item.code}
                                                    </span>
                                                    {!item.is_active && (
                                                        <span className="ml-1 text-xs text-amber-600 dark:text-amber-400">
                                                            (inactiva)
                                                        </span>
                                                    )}
                                                </li>
                                            ))}
                                        </ul>
                                    </td>
                                    <td className="p-3">
                                        <span
                                            className={
                                                type.is_active
                                                    ? 'rounded-full bg-emerald-500/15 px-2 py-1 text-xs font-medium text-emerald-600 dark:text-emerald-300'
                                                    : 'rounded-full bg-slate-500/15 px-2 py-1 text-xs font-medium text-slate-600 dark:text-slate-300'
                                            }
                                        >
                                            {type.is_active
                                                ? 'Activo'
                                                : 'Inactivo'}
                                        </span>
                                    </td>
                                    <td className="p-3 text-right">
                                        <TableActions
                                            permissions={{
                                                view: false,
                                                edit: type.can.update,
                                                delete: type.can.delete,
                                            }}
                                            onEdit={() =>
                                                router.get(
                                                    typesEdit(type.id).url,
                                                )
                                            }
                                            onDelete={() => handleDelete(type)}
                                            tooltips={{
                                                delete: 'Eliminar tipo',
                                            }}
                                        />
                                    </td>
                                </tr>
                            ))}
                            {types.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="p-8 text-center text-sm text-muted-foreground"
                                    >
                                        No se encontraron tipos de
                                        termoencogido.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex justify-center">
                    <Pagination links={types.links} />
                </div>
            </div>
        </>
    );
}

ShrinkWrapTypesIndex.layout = {
    breadcrumbs: [{ title: 'Tipos de termoencogido', href: typesIndex() }],
    wide: true,
};
