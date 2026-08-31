import { Head, Link, router, usePage } from '@inertiajs/react';
import { BellRing } from 'lucide-react';
import type { ComponentProps } from 'react';

import { DataTableFilters } from '@/components/data-table-filters';
import { FormattedNumber } from '@/components/formatted-number';
import { TableActions } from '@/components/table-actions';
import { Button } from '@/components/ui/button';
import Pagination from '@/components/ui/pagination';
import { useFilters } from '@/hooks/use-filters';
import { withReturnTo } from '@/lib/navigation';
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
    is_active: boolean;
    unit_of_measure: { id: number; name: string; symbol: string } | null;
    can: {
        view: boolean;
        update: boolean;
        delete: boolean;
        reactivate: boolean;
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

    const flash = usePage<{
        flash?: { success?: string; error?: string };
    }>().props.flash;

    const handleDelete = (id: number) => {
        if (
            !window.confirm(
                '¿Estás seguro de que quieres eliminar o desactivar esta materia prima? (El sistema determinará la acción según su historial)',
            )
        ) {
            return;
        }

        router.delete(rawMaterialsDestroy({ raw_material: id }).url, {
            preserveScroll: true,
        });
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

    return (
        <>
            <Head title="Materias Primas" />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
                            Materias Primas
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Inventario base de materias primas de planta.
                        </p>
                    </div>

                    {can.create && (
                        <Button asChild>
                            <Link href={rawMaterialsCreate().url}>
                                Nueva Materia Prima
                            </Link>
                        </Button>
                    )}
                </div>

                {/* Alertas */}
                {flash?.success && (
                    <div className="rounded-md border border-emerald-500/25 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-700 dark:text-emerald-300">
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

                {/* Tabla */}
                <div className="overflow-x-auto rounded-lg border border-border bg-card">
                    <table className="w-full text-sm">
                        <thead className="border-b border-border bg-muted/40">
                            <tr>
                                <th className="p-3 text-left font-medium">
                                    Código
                                </th>
                                <th className="p-3 text-left font-medium">
                                    Unidad
                                </th>
                                {can.view_costs && (
                                    <th className="p-3 text-right font-medium">
                                        Precio
                                    </th>
                                )}
                                <th className="p-3 text-right font-medium">
                                    Stock Disponible
                                </th>
                                <th className="p-3 text-center font-medium">
                                    Alertas
                                </th>
                                <th className="p-3 text-center font-medium">
                                    Estado
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Acciones
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            {rawMaterials.data.map((item) => (
                                <tr
                                    key={item.id}
                                    className="border-b border-border/60 transition last:border-0 hover:bg-muted/30"
                                >
                                    <td className="p-3 font-medium text-foreground">
                                        {item.code}
                                    </td>

                                    <td className="p-3 text-muted-foreground">
                                        {item.unit_of_measure
                                            ? `${item.unit_of_measure.name} (${item.unit_of_measure.symbol})`
                                            : '-'}
                                    </td>

                                    {can.view_costs && (
                                        <td className="p-3 text-right">
                                            <FormattedNumber
                                                value={item.current_price}
                                                currency
                                                maxDecimals={2}
                                            />
                                        </td>
                                    )}

                                    <td className="p-3 text-right">
                                        <FormattedNumber
                                            value={item.available_stock}
                                            maxDecimals={2}
                                        />
                                    </td>

                                    <td className="p-3 text-center">
                                        {item.active_alerts_count > 0 ? (
                                            <Link
                                                href={`${alertsIndex().url}?status=active`}
                                                className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium ${
                                                    item.has_critical_alert
                                                        ? 'bg-red-500/15 text-red-700 dark:text-red-300'
                                                        : 'bg-amber-500/15 text-amber-700 dark:text-amber-300'
                                                }`}
                                                title="Ver alertas activas"
                                            >
                                                <BellRing className="h-3.5 w-3.5" />
                                                {item.active_alerts_count}
                                            </Link>
                                        ) : (
                                            <span className="text-xs text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </td>

                                    <td className="p-3 text-center">
                                        <span
                                            className={
                                                item.is_active
                                                    ? 'rounded-full bg-emerald-500/15 px-2 py-1 text-xs font-medium text-emerald-600 dark:text-emerald-300'
                                                    : 'rounded-full bg-slate-500/15 px-2 py-1 text-xs font-medium text-slate-600 dark:text-slate-300'
                                            }
                                        >
                                            {item.is_active
                                                ? 'Activo'
                                                : 'Inactivo'}
                                        </span>
                                    </td>

                                    <td className="p-3 text-right">
                                        <div className="flex justify-end gap-2">
                                            <TableActions
                                                permissions={{
                                                    view: item.can.view,
                                                    edit: item.can.update,
                                                    delete: item.can.delete,
                                                }}
                                                onView={() =>
                                                    router.get(
                                                        withReturnTo(
                                                            rawMaterialsShow({
                                                                raw_material:
                                                                    item.id,
                                                            }).url,
                                                        ),
                                                    )
                                                }
                                                onEdit={() =>
                                                    router.get(
                                                        rawMaterialsEdit({
                                                            raw_material:
                                                                item.id,
                                                        }).url,
                                                    )
                                                }
                                                onDelete={() =>
                                                    handleDelete(item.id)
                                                }
                                            />

                                            {item.can.reactivate && (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        handleReactivate(
                                                            item.id,
                                                        )
                                                    }
                                                >
                                                    Reactivar
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}

                            {/* Empty State */}
                            {rawMaterials.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={can.view_costs ? 7 : 6}
                                        className="p-10 text-center text-sm text-muted-foreground"
                                    >
                                        No se encontraron materias primas.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Paginación */}
                <div className="mt-4 flex justify-center">
                    <Pagination links={rawMaterials.links} />
                </div>
            </div>
        </>
    );
}
