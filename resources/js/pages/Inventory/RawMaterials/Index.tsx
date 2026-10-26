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
import { Badge } from '@/components/ui/badge';
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
    category: { id: number; name: string; type_label: string } | null;
    can: {
        view: boolean;
        update: boolean;
        delete: boolean;
        reactivate: boolean;
        forceDelete: boolean;
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
            cell: (item) =>
                item.category ? (
                    <div className="flex flex-col items-start gap-1">
                        <span className="text-foreground">
                            {item.category.name}
                        </span>
                        <Badge
                            variant="secondary"
                            className="text-xs font-normal"
                        >
                            {item.category.type_label}
                        </Badge>
                    </div>
                ) : (
                    '-'
                ),
            mobile: 'subtitle',
        },
        {
            key: 'unit',
            header: 'Unidad',
            cell: (item) =>
                item.unit_of_measure
                    ? `${item.unit_of_measure.name} (${item.unit_of_measure.symbol})`
                    : '-',
            mobile: 'meta',
            className: 'text-muted-foreground',
        },
        ...(can.view_costs
            ? [
                  {
                      key: 'price',
                      header: 'Precio',
                      cell: (item: RawMaterialRow) => (
                          <FormattedNumber
                              value={item.current_price}
                              currency
                              maxDecimals={2}
                          />
                      ),
                      mobile: 'meta' as const,
                      align: 'right' as const,
                  },
              ]
            : []),
        {
            key: 'stock',
            header: 'Stock disponible',
            cell: (item) => (
                <FormattedNumber value={item.available_stock} maxDecimals={2} />
            ),
            mobile: 'meta',
            align: 'right',
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
                ) : (
                    <span className="text-xs text-muted-foreground">—</span>
                ),
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
                        // Se elimina solo sin historial y con permiso de borrado físico; si no, el servidor desactiva.
                        // Con stock en bodega no se puede ninguna.
                        item.can.forceDelete && !item.has_activity
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
                canDeletePermanently={deleteTarget?.can.forceDelete ?? false}
                processing={deleteProcessing}
                onConfirm={handleDeleteConfirm}
            />
        </>
    );
}
