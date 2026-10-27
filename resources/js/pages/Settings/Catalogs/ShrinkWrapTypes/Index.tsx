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
    create as typesCreate,
    destroy as typesDestroy,
    edit as typesEdit,
    index as typesIndex,
} from '@/routes/catalogs/shrink-wrap-types';
import type { ShrinkWrapTypeRow } from '@/types';
import type { PaginationLink } from '@/types/ui';

type TypeRow = ShrinkWrapTypeRow & {
    in_use: boolean;
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

    const columns: DataListingColumn<TypeRow>[] = [
        {
            key: 'name',
            header: 'Nombre',
            cell: (type) => type.name,
            mobile: 'title',
        },
        {
            key: 'items',
            header: 'Consumo por aplicación',
            cell: (type) => (
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
                                <span className="ml-1 text-xs text-warning-text">
                                    (inactiva)
                                </span>
                            )}
                        </li>
                    ))}
                </ul>
            ),
            mobile: 'meta',
            className: 'text-muted-foreground',
        },
        {
            key: 'status',
            header: 'Estado',
            cell: (type) => <ActiveBadge isActive={type.is_active} />,
            mobile: 'status',
        },
    ];

    return (
        <>
            <Head title="Tipos de termoencogido" />

            <div className="space-y-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <Heading
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

                {/* Sin detalle: el nombre abre la edición. */}
                <DataListing
                    rows={types.data}
                    rowKey={(type) => type.id}
                    rowHref={(type) =>
                        type.can.update ? typesEdit(type.id).url : undefined
                    }
                    rowLabel={(type) => type.name}
                    columns={columns}
                    // El nombre ya abre la edición: el menú no la repite (DESIGN.md §9).
                    actions={(type) => [
                        {
                            label: 'Eliminar',
                            icon: Trash2,
                            destructive: true,
                            onSelect: () => handleDelete(type),
                            // Con registros no se puede eliminar (clave foránea RESTRICT): se desactiva.
                            disabledReason: type.in_use
                                ? 'No disponible: ya tiene termoencogidos registrados. Desactívalo al editarlo.'
                                : undefined,
                            hidden: !type.can.delete,
                        },
                    ]}
                    empty="No se encontraron tipos de termoencogido."
                />

                <div className="flex justify-center">
                    <Pagination links={types.links} />
                </div>
            </div>
        </>
    );
}

ShrinkWrapTypesIndex.layout = {
    wide: true,
};
