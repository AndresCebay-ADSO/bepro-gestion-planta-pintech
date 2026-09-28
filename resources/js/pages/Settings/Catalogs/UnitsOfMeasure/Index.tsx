import { Head, Link, router } from '@inertiajs/react';
import {
    UnitEquivalence,
    describeUnitUsage,
} from '@/components/catalogs/unit-of-measure-form';
import { DataTableFilters } from '@/components/data-table-filters';
import Heading from '@/components/heading';
import { TableActions } from '@/components/table-actions';
import { Button } from '@/components/ui/button';
import Pagination from '@/components/ui/pagination';
import { useFilters } from '@/hooks/use-filters';
import {
    create as unitsCreate,
    destroy as unitsDestroy,
    edit as unitsEdit,
    index as unitsIndex,
} from '@/routes/catalogs/units-of-measure';
import type { UnitOfMeasureRow } from '@/types';
import type { PaginationLink } from '@/types/ui';

type UnitRow = UnitOfMeasureRow & {
    can: {
        update: boolean;
        delete: boolean;
    };
};

type Props = {
    units: {
        data: UnitRow[];
        links: PaginationLink[];
    };
    filters: Record<string, string | null | undefined>;
    can: {
        create: boolean;
    };
};

export default function UnitsOfMeasureIndex({ units, filters, can }: Props) {
    const {
        filters: filterState,
        setFilter,
        clearFilters,
    } = useFilters({
        routeUrl: unitsIndex().url,
        initialFilters: {
            search: filters.search ?? '',
            status: filters.status ?? '',
        },
    });

    const handleDelete = (unit: UnitRow) => {
        if (
            !window.confirm(
                `¿Eliminar la unidad «${unit.name}»? Solo se puede si nada la usa; si no, desactívala.`,
            )
        ) {
            return;
        }

        router.delete(unitsDestroy(unit.id).url, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Unidades de medida" />

            <div className="space-y-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <Heading
                        variant="small"
                        title="Unidades de medida"
                        description="Con qué unidad se compran, formulan y venden los productos. Su equivalencia convierte las cantidades de las fórmulas."
                    />
                    {can.create && (
                        <Button asChild>
                            <Link href={unitsCreate().url}>Nueva unidad</Link>
                        </Button>
                    )}
                </div>

                <DataTableFilters
                    fields={[
                        {
                            type: 'text',
                            name: 'search',
                            label: 'Buscar',
                            placeholder:
                                'Buscar por código, nombre o símbolo...',
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
                                    Símbolo
                                </th>
                                <th className="p-3 text-left font-medium text-foreground">
                                    Equivalencia
                                </th>
                                <th className="p-3 text-left font-medium text-foreground">
                                    En uso
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
                            {units.data.map((unit) => (
                                <tr
                                    key={unit.id}
                                    className="border-b border-border/60 last:border-0"
                                >
                                    <td className="p-3 font-mono text-foreground">
                                        {unit.code}
                                    </td>
                                    <td className="p-3 text-foreground">
                                        {unit.name}
                                    </td>
                                    <td className="p-3 text-muted-foreground">
                                        {unit.symbol}
                                    </td>
                                    <td className="p-3 whitespace-nowrap">
                                        <span
                                            className={
                                                unit.to_kg_conversion ||
                                                unit.to_liter_conversion
                                                    ? ''
                                                    : 'text-muted-foreground'
                                            }
                                        >
                                            <UnitEquivalence
                                                symbol={unit.symbol}
                                                kg={unit.to_kg_conversion}
                                                liter={unit.to_liter_conversion}
                                            />
                                        </span>
                                    </td>
                                    <td className="p-3 text-muted-foreground">
                                        {describeUnitUsage(unit.usage)}
                                    </td>
                                    <td className="p-3">
                                        <span
                                            className={
                                                unit.is_active
                                                    ? 'rounded-full bg-emerald-500/15 px-2 py-1 text-xs font-medium text-emerald-600 dark:text-emerald-300'
                                                    : 'rounded-full bg-slate-500/15 px-2 py-1 text-xs font-medium text-slate-600 dark:text-slate-300'
                                            }
                                        >
                                            {unit.is_active
                                                ? 'Activa'
                                                : 'Inactiva'}
                                        </span>
                                    </td>
                                    <td className="p-3 text-right">
                                        <TableActions
                                            permissions={{
                                                view: false,
                                                edit: unit.can.update,
                                                delete: unit.can.delete,
                                            }}
                                            onEdit={() =>
                                                router.get(
                                                    unitsEdit(unit.id).url,
                                                )
                                            }
                                            onDelete={() => handleDelete(unit)}
                                            // En uso no se puede eliminar (claves foráneas RESTRICT): se desactiva.
                                            disabled={{ delete: unit.in_use }}
                                            tooltips={{
                                                delete: unit.in_use
                                                    ? 'En uso: no se puede eliminar. Desactívala al editarla.'
                                                    : 'Eliminar unidad',
                                            }}
                                        />
                                    </td>
                                </tr>
                            ))}
                            {units.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="p-8 text-center text-sm text-muted-foreground"
                                    >
                                        No se encontraron unidades de medida.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex justify-center">
                    <Pagination links={units.links} />
                </div>
            </div>
        </>
    );
}

UnitsOfMeasureIndex.layout = {
    breadcrumbs: [{ title: 'Unidades de medida', href: unitsIndex() }],
    wide: true,
};
