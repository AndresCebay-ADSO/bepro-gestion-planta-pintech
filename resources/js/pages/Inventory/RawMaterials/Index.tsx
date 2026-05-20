import { Head, useForm, Link, router, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';

import RawMaterialController from '@/actions/App/Http/Controllers/Inventory/RawMaterialController';

import { FormattedNumber } from '@/components/formatted-number';
import { TableActions } from '@/components/table-actions';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Pagination from '@/components/ui/pagination';
import type { PaginationLink } from '@/types/ui';

/**
 * Types
 */
type RawMaterialRow = {
    id: number;
    code: string;
    current_price: string | null;
    previous_price: string | null;
    minimum_stock: string;
    available_stock: string | number;
    alert_days_before_expiry: number;
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
        search: string;
        status: 'active' | 'inactive' | 'all';
    };
    can: {
        create: boolean;
        view_costs: boolean;
    };
};

/**
 * Main Component
 */
export default function RawMaterialsIndex({
    rawMaterials,
    filters,
    can,
}: Props) {
    const { data, setData, get } = useForm({
        search: filters.search ?? '',
        status: filters.status ?? 'active',
    });

    const flash = usePage<{
        flash?: { success?: string; error?: string };
    }>().props.flash;

    /**
     * Search
     */
    const handleSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        get(RawMaterialController.index.url(), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    /**
     * Delete
     */
    const handleDelete = (code: string) => {
        if (
            !window.confirm(
                '¿Estás seguro de que quieres eliminar o desactivar esta materia prima? (El sistema determinará la acción según su historial)',
            )
        ) {
            return;
        }

        router.delete(RawMaterialController.destroy.url(code), {
            preserveScroll: true,
        });
    };

    const handleReactivate = (code: string) => {
        router.patch(RawMaterialController.reactivate.url(code), undefined, {
            preserveScroll: true,
        });
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
                            <Link href={RawMaterialController.create.url()}>
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
                <form
                    onSubmit={handleSearch}
                    className="flex flex-col gap-2 sm:flex-row"
                >
                    <Input
                        value={data.search}
                        onChange={(e) => setData('search', e.target.value)}
                        placeholder="Buscar por código..."
                        className="sm:max-w-sm"
                    />
                    <Button type="submit" variant="outline">
                        Buscar
                    </Button>
                    <select
                        value={data.status}
                        onChange={(e) =>
                            setData(
                                'status',
                                e.target.value as 'active' | 'inactive' | 'all',
                            )
                        }
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground"
                    >
                        <option value="active">Activas</option>
                        <option value="inactive">Inactivas</option>
                        <option value="all">Todas</option>
                    </select>
                </form>

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
                                                        RawMaterialController.show.url(
                                                            item.code,
                                                        ),
                                                    )
                                                }
                                                onEdit={() =>
                                                    router.get(
                                                        RawMaterialController.edit.url(
                                                            item.code,
                                                        ),
                                                    )
                                                }
                                                onDelete={() =>
                                                    handleDelete(item.code)
                                                }
                                            />

                                            {item.can.reactivate && (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        handleReactivate(
                                                            item.code,
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
                                        colSpan={can.view_costs ? 6 : 5}
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
