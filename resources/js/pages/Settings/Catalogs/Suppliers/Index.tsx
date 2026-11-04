import { Head, Link, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';

import { ActiveBadge } from '@/components/active-badge';
import { DataListing } from '@/components/data-listing';
import type { DataListingColumn } from '@/components/data-listing';
import { DataTableFilters } from '@/components/data-table-filters';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/hooks/use-confirm';
import { useFilters } from '@/hooks/use-filters';
import {
    create as suppliersCreate,
    destroy as suppliersDestroy,
    edit as suppliersEdit,
    index as suppliersIndex,
} from '@/routes/catalogs/suppliers';
import type { SupplierRow } from '@/types';
import type { Paginated } from '@/types/ui';

type Row = SupplierRow & {
    in_use: boolean;
    can: {
        update: boolean;
        delete: boolean;
    };
};

type Props = {
    suppliers: Paginated<Row>;
    filters: Record<string, string | null | undefined>;
    can: {
        create: boolean;
    };
};

export default function SuppliersIndex({ suppliers, filters, can }: Props) {
    const {
        filters: filterState,
        setFilter,
        clearFilters,
    } = useFilters({
        routeUrl: suppliersIndex().url,
        initialFilters: {
            search: filters.search ?? '',
            status: filters.status ?? '',
        },
    });

    const confirm = useConfirm();

    const handleDelete = async (supplier: Row) => {
        if (
            !(await confirm({
                title: `¿Eliminar al proveedor «${supplier.name}»?`,
                description:
                    'Solo se puede si no tiene lotes; si los tiene, desactívalo.',
                confirmLabel: 'Eliminar',
                destructive: true,
            }))
        ) {
            return;
        }

        router.delete(suppliersDestroy(supplier.id).url, {
            preserveScroll: true,
        });
    };

    const columns: DataListingColumn<Row>[] = [
        {
            key: 'name',
            header: 'Nombre',
            cell: (supplier) => supplier.name,
            mobile: 'title',
            // El texto principal de la fila salta de línea (DESIGN.md §9): un nombre largo no empuja el menú fuera de la vista.
            className: 'whitespace-normal',
        },
        {
            key: 'nit',
            header: 'NIT',
            cell: (supplier) => supplier.nit,
            mobile: 'meta',
            className: 'text-muted-foreground',
        },
        {
            key: 'contact_name',
            header: 'Contacto',
            cell: (supplier) => supplier.contact_name,
            mobile: 'meta',
            className: 'text-muted-foreground',
        },
        {
            key: 'phone',
            header: 'Teléfono',
            cell: (supplier) => supplier.phone,
            mobile: 'meta',
            className: 'text-muted-foreground',
        },
        {
            key: 'status',
            header: 'Estado',
            cell: (supplier) => <ActiveBadge isActive={supplier.is_active} />,
            mobile: 'status',
        },
    ];

    return (
        <>
            <Head title="Proveedores" />

            <div className="space-y-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <Heading
                        title="Proveedores"
                        description="A quién se le compra la materia prima. Se elige al registrar la entrada que crea un lote."
                    />
                    {can.create && (
                        <Button asChild>
                            <Link href={suppliersCreate().url}>
                                Nuevo proveedor
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
                            placeholder: 'Buscar por nombre, NIT o contacto...',
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
                    rows={suppliers.data}
                    rowKey={(supplier) => supplier.id}
                    rowHref={(supplier) =>
                        supplier.can.update
                            ? suppliersEdit(supplier.id).url
                            : undefined
                    }
                    rowLabel={(supplier) => supplier.name}
                    columns={columns}
                    // El nombre ya abre la edición: el menú no la repite (DESIGN.md §9).
                    actions={(supplier) => [
                        {
                            label: 'Eliminar',
                            icon: Trash2,
                            destructive: true,
                            onSelect: () => handleDelete(supplier),
                            // Con lotes no se puede eliminar (clave foránea RESTRICT): se desactiva.
                            disabledReason: supplier.in_use
                                ? 'No disponible: tiene lotes. Desactívalo al editarlo.'
                                : undefined,
                            hidden: !supplier.can.delete,
                        },
                    ]}
                    empty="No se encontraron proveedores."
                />

                <Pagination paginator={suppliers} />
            </div>
        </>
    );
}

SuppliersIndex.layout = {
    wide: true,
};
