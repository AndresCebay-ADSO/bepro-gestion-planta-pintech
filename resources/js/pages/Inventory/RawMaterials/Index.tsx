import { Head, Link, router, usePage } from '@inertiajs/react';
import { BellRing, Pencil, Power, PowerOff, Trash2 } from 'lucide-react';
import type { ComponentProps } from 'react';
import { useState } from 'react';

import { ActiveBadge } from '@/components/active-badge';
import { DataListing } from '@/components/data-listing';
import type { DataListingColumn } from '@/components/data-listing';
import { DataTableFilters } from '@/components/data-table-filters';
import { FormattedNumber } from '@/components/formatted-number';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import Pagination from '@/components/ui/pagination';
import { useFilters } from '@/hooks/use-filters';
import { withReturnTo } from '@/lib/navigation';
import { DeleteRawMaterialDialog } from '@/pages/Inventory/RawMaterials/DeleteRawMaterialDialog';
import { index as alertsIndex } from '@/routes/alerts';
import {
    create as rawMaterialsCreate,
    destroy as rawMaterialsDestroy,
    edit as rawMaterialsEdit,
    index as rawMaterialsIndex,
    reactivate as rawMaterialsReactivate,
    show as rawMaterialsShow,
} from '@/routes/raw-materials';
import type { PaginationLink } from '@/types/ui';

type RawMaterialRow = {
    id: number;
    code: string;
    current_price: string | null;
    previous_price: string | null;
    minimum_stock: string;
    available_stock: string | number;
    alert_days_before_expiry: number;
    active_alerts_count: number;
    has_critical_alert: boolean;
    has_available_stock: boolean;
    has_activity: boolean;
    is_active: boolean;
    unit_of_measure: { id: number; name: string; symbol: string } | null;
    category: { id: number; name: string } | null;
    can: {
        view: boolean;
        update: boolean;
        delete: boolean;
        reactivate: boolean;
        deletePermanently: boolean;
    };
};

type Props = {
    rawMaterials: {
        data: RawMaterialRow[];
        links: PaginationLink[];
    };
    filters: {
        search?: string;
        status?: string;
    };
    can: {
        create: boolean;
        view_costs: boolean;
    };
};

const statusOptions = [
    { value: 'active', label: 'Activas' },
    { value: 'inactive', label: 'Inactivas' },
];

/**
 * Raw materials index page displaying listing, stock, alerts, and actions.
 */
export default function RawMaterialsIndex({
    rawMaterials,
    filters,
    can,
}: Props) {
    const {
        filters: filterState,
        setFilter,
        setFilterImmediate,
        clearFilters,
    } = useFilters({
        routeUrl: rawMaterialsIndex().url,
        initialFilters: filters,
    });

    const filterFields: ComponentProps<typeof DataTableFilters>['fields'] = [
        {
            type: 'text',
            name: 'search',
            label: 'Buscar',
            placeholder: 'Buscar por código…',
        },
        {
            type: 'select',
            name: 'status',
            label: 'Estado',
            options: statusOptions,
        },
    ];

    const flash = usePage().props.flash;

    const [deleteTarget, setDeleteTarget] = useState<RawMaterialRow | null>(
        null,
    );
    const [deleteProcessing, setDeleteProcessing] = useState(false);

    const handleDeleteConfirm = () => {
        if (!deleteTarget) {
            return;
        }

        setDeleteProcessing(true);
        router.delete(
            rawMaterialsDestroy({ raw_material: deleteTarget.id }).url,
            {
                preserveScroll: true,
                onFinish: () => {
                    setDeleteProcessing(false);
                    setDeleteTarget(null);
                },
            },
        );
    };

    const handleReactivate = (id: number) => {
        router.patch(
            rawMaterialsReactivate({ raw_material: id }).url,
            undefined,
            {
                preserveScroll: true,
            },
        );
    };

    const columns: DataListingColumn<RawMaterialRow>[] = [
        {
            key: 'code',
            header: 'Código',
            cell: (item) => item.code,
            mobile: 'title',
            className: 'font-mono',
            cardClassName: 'font-mono',
        },
        {
            key: 'category',
            header: 'Categoría',
            cell: (item) => item.category?.name ?? null,
            mobile: 'subtitle',
        },
        ...(can.view_costs
            ? [
                  {
                      key: 'price',
                      header: 'Precio',
                      // Sin precio es un dato que falta (sin compras registradas), no un vacío: se dice.
                      cell: (item: RawMaterialRow) => (
                          <FormattedNumber
                              value={item.current_price}
                              currency
                              maxDecimals={2}
                              emptyValue="Sin precio"
                          />
                      ),
                      mobile: 'meta' as const,
                      align: 'right' as const,
                  },
              ]
            : []),
        // El stock lleva el símbolo de la unidad («1.000 u»): no hace falta una columna Unidad.
        {
            key: 'stock',
            header: 'Stock disponible',
            cell: (item) => (
                <>
                    <FormattedNumber
                        value={item.available_stock}
                        maxDecimals={2}
                    />
                    {item.unit_of_measure && ` ${item.unit_of_measure.symbol}`}
                </>
            ),
            mobile: 'primary',
            align: 'right',
            className: 'whitespace-nowrap',
        },
        {
            key: 'alerts',
            header: 'Alertas',
            cell: (item) =>
                item.active_alerts_count > 0 ? (
                    <Link
                        href={alertsIndex({ query: { status: 'active' } }).url}
                        className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium ${
                            item.has_critical_alert
                                ? 'bg-destructive/15 text-destructive-text'
                                : 'bg-warning/15 text-warning-text'
                        }`}
                        title="Ver alertas activas"
                    >
                        <BellRing className="h-3.5 w-3.5" />
                        {item.active_alerts_count}
                    </Link>
                ) : null,
            // Pide acción: va también en la tarjeta (DESIGN.md §9).
            mobile: 'meta',
            align: 'center',
        },
        {
            key: 'status',
            header: 'Estado',
            cell: (item) => <ActiveBadge isActive={item.is_active} feminine />,
            mobile: 'status',
            align: 'center',
        },
    ];

    return (
        <>
            <Head title="Materias primas" />

            <div className="space-y-6 p-6">
                <PageHeader
                    title="Materias primas"
                    description="Inventario base de materias primas de planta."
                    actions={
                        can.create && (
                            <Button asChild>
                                <Link href={rawMaterialsCreate().url}>
                                    Nueva materia prima
                                </Link>
                            </Button>
                        )
                    }
                />

                {/* Alertas */}
                {flash?.success && (
                    <div className="rounded-md border border-success/30 bg-success/15 px-4 py-2 text-sm text-success-text">
                        {flash.success}
                    </div>
                )}

                {flash?.error && (
                    <div className="rounded-md border border-destructive/30 bg-destructive/10 px-4 py-2 text-sm text-destructive">
                        {flash.error}
                    </div>
                )}

                {/* Search */}
                <DataTableFilters
                    fields={filterFields}
                    filters={filterState}
                    onFilter={setFilter}
                    onFilterImmediate={setFilterImmediate}
                    onClear={clearFilters}
                />

                <DataListing
                    rows={rawMaterials.data}
                    rowKey={(item) => item.id}
                    rowHref={(item) =>
                        item.can.view
                            ? withReturnTo(
                                  rawMaterialsShow({ raw_material: item.id })
                                      .url,
                              )
                            : undefined
                    }
                    rowLabel={(item) => item.code}
                    columns={columns}
                    actions={(item) => [
                        {
                            label: 'Editar',
                            icon: Pencil,
                            href: rawMaterialsEdit({ raw_material: item.id })
                                .url,
                            hidden: !item.can.update,
                        },
                        {
                            label: 'Reactivar',
                            icon: Power,
                            onSelect: () => handleReactivate(item.id),
                            hidden: !item.can.reactivate,
                        },
                        // El servidor decide si se elimina o se desactiva. Con stock en bodega no se puede ninguna.
                        item.can.deletePermanently
                            ? {
                                  label: 'Eliminar',
                                  icon: Trash2,
                                  destructive: true,
                                  onSelect: () => setDeleteTarget(item),
                                  hidden: !item.can.delete,
                              }
                            : {
                                  label: 'Desactivar',
                                  icon: PowerOff,
                                  onSelect: () => setDeleteTarget(item),
                                  disabledReason: item.has_available_stock
                                      ? 'No disponible: tiene stock en bodega'
                                      : undefined,
                                  hidden: !item.can.delete,
                              },
                    ]}
                    empty="No se encontraron materias primas."
                />

                {/* Paginación */}
                <div className="mt-4 flex justify-center">
                    <Pagination links={rawMaterials.links} />
                </div>
            </div>

            <DeleteRawMaterialDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleteTarget(null);
                    }
                }}
                rawMaterial={deleteTarget}
                hasActivity={deleteTarget?.has_activity ?? false}
                deletesPermanently={
                    deleteTarget?.can.deletePermanently ?? false
                }
                processing={deleteProcessing}
                onConfirm={handleDeleteConfirm}
            />
        </>
    );
}
