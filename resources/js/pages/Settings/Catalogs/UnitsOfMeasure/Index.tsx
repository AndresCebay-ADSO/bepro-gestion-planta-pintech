import { Head, Link, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';

import { ActiveBadge } from '@/components/active-badge';
import {
    UnitEquivalence,
    describeUnitUsage,
} from '@/components/catalogs/unit-of-measure-form';
import { DataListing } from '@/components/data-listing';
import type { DataListingColumn } from '@/components/data-listing';
import { DataTableFilters } from '@/components/data-table-filters';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/hooks/use-confirm';
import { useFilters } from '@/hooks/use-filters';
import {
    create as unitsCreate,
    destroy as unitsDestroy,
    edit as unitsEdit,
    index as unitsIndex,
} from '@/routes/catalogs/units-of-measure';
import type { UnitOfMeasureRow } from '@/types';
import type { Paginated } from '@/types/ui';

type UnitRow = UnitOfMeasureRow & {
    can: {
        update: boolean;
        delete: boolean;
    };
};

type Props = {
    units: Paginated<UnitRow>;
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

    const confirm = useConfirm();

    const handleDelete = async (unit: UnitRow) => {
        if (
            !(await confirm({
                title: `¿Eliminar la unidad «${unit.name}»?`,
                description:
                    'Solo se puede si nada la usa; si no, desactívala.',
                confirmLabel: 'Eliminar',
                destructive: true,
            }))
        ) {
            return;
        }

        router.delete(unitsDestroy(unit.id).url, { preserveScroll: true });
    };

    const columns: DataListingColumn<UnitRow>[] = [
        {
            key: 'code',
            header: 'Código',
            cell: (unit) => unit.code,
            mobile: 'title',
            className: 'font-mono',
            cardClassName: 'font-mono',
        },
        {
            key: 'name',
            header: 'Nombre',
            cell: (unit) => unit.name,
            mobile: 'subtitle',
        },
        {
            key: 'symbol',
            header: 'Símbolo',
            cell: (unit) => unit.symbol,
            mobile: 'meta',
            className: 'text-muted-foreground',
        },
        {
            key: 'equivalence',
            header: 'Equivalencia',
            cell: (unit) => (
                <span
                    className={
                        unit.to_kg_conversion || unit.to_liter_conversion
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
            ),
            mobile: 'meta',
            className: 'whitespace-nowrap',
        },
        {
            key: 'usage',
            header: 'En uso',
            cell: (unit) => describeUnitUsage(unit.usage),
            mobile: 'meta',
            className: 'text-muted-foreground',
        },
        {
            key: 'status',
            header: 'Estado',
            cell: (unit) => <ActiveBadge isActive={unit.is_active} feminine />,
            mobile: 'status',
        },
    ];

    return (
        <>
            <Head title="Unidades de medida" />

            <div className="space-y-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <Heading
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

                {/* Sin detalle: el código abre la edición. */}
                <DataListing
                    rows={units.data}
                    rowKey={(unit) => unit.id}
                    rowHref={(unit) =>
                        unit.can.update ? unitsEdit(unit.id).url : undefined
                    }
                    rowLabel={(unit) => unit.code}
                    columns={columns}
                    // El código ya abre la edición: el menú no la repite (DESIGN.md §9).
                    actions={(unit) => [
                        {
                            label: 'Eliminar',
                            icon: Trash2,
                            destructive: true,
                            onSelect: () => handleDelete(unit),
                            // En uso no se puede eliminar (claves foráneas RESTRICT): se desactiva.
                            // La salida («desactívala al editarla») solo se ofrece a quien puede editar.
                            disabledReason: unit.in_use
                                ? unit.can.update
                                    ? 'No disponible: está en uso. Desactívala al editarla.'
                                    : 'No disponible: está en uso.'
                                : undefined,
                            hidden: !unit.can.delete,
                        },
                    ]}
                    empty="No se encontraron unidades de medida."
                />

                <Pagination paginator={units} />
            </div>
        </>
    );
}

UnitsOfMeasureIndex.layout = {
    wide: true,
};
