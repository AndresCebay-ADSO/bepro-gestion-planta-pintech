import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';

import RawMaterialController from '@/actions/App/Http/Controllers/Inventory/RawMaterialController';

import { FormattedNumber } from '@/components/formatted-number';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

/**
 * Tipos
 */
type RawMaterialRow = {
    id: number;
    code: string;
    current_price: string;
    previous_price: string | null;
    minimum_stock: string;
    alert_days_before_expiry: number;
    is_active: boolean;
    unit_of_measure: { id: number; name: string; symbol: string } | null;
    can: {
        view: boolean;
        update: boolean;
        delete: boolean;
    };
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    rawMaterials: {
        data: RawMaterialRow[];
        links: PaginationLink[];
    };
    filters: {
        search: string;
    };
    can: {
        create: boolean;
    };
};

/**
 * Componente principal
 */
export default function RawMaterialsIndex({ rawMaterials, filters, can }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const flash = usePage<{
        flash?: { success?: string; error?: string };
    }>().props.flash;

    /**
     * Limpiar paginación
     */
    const paginationLinks = useMemo(
        () =>
            rawMaterials.links.filter(
                (link) =>
                    !link.label.includes('Previous') &&
                    !link.label.includes('Next') &&
                    !link.label.includes('previous') &&
                    !link.label.includes('next')
            ),
        [rawMaterials.links]
    );

    /**
     * Buscar
     */
    const handleSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        router.get(
            RawMaterialController.index.url(),
            { search },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    /**
     * Eliminar
     */
    const handleDelete = (code: string) => {
        if (!window.confirm('¿Estás seguro de eliminar esta materia prima?')) {
            return;
        }

        router.delete(RawMaterialController.destroy.url(code), {
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

                {/* Buscador */}
                <form
                    onSubmit={handleSearch}
                    className="flex flex-col gap-2 sm:flex-row"
                >
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Buscar por código..."
                        className="sm:max-w-sm"
                    />
                    <Button type="submit" variant="outline">
                        Buscar
                    </Button>
                </form>

                {/* Tabla */}
                <div className="overflow-x-auto rounded-lg border border-border bg-card">
                    <table className="w-full text-sm">

                        <thead className="border-b border-border bg-muted/40">
                            <tr>
                                <th className="p-3 text-left font-medium">Código</th>
                                <th className="p-3 text-left font-medium">Unidad</th>
                                <th className="p-3 text-right font-medium">Precio</th>
                                <th className="p-3 text-right font-medium">Stock Mínimo</th>
                                <th className="p-3 text-center font-medium">Estado</th>
                                <th className="p-3 text-right font-medium">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            {rawMaterials.data.map((item) => (
                                <tr
                                    key={item.id}
                                    className="border-b border-border/60 last:border-0 hover:bg-muted/30 transition"
                                >
                                    <td className="p-3 font-medium text-foreground">
                                        {item.code}
                                    </td>

                                    <td className="p-3 text-muted-foreground">
                                        {item.unit_of_measure
                                            ? `${item.unit_of_measure.name} (${item.unit_of_measure.symbol})`
                                            : '-'}
                                    </td>

                                    <td className="p-3 text-right">
                                        <FormattedNumber
                                            value={item.current_price}
                                            currency
                                        />
                                    </td>

                                    <td className="p-3 text-right">
                                        <FormattedNumber
                                            value={item.minimum_stock}
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
                                                ? 'Activa'
                                                : 'Inactiva'}
                                        </span>
                                    </td>

                                    <td className="p-3 text-right">
                                        <div className="flex justify-end gap-2">

                                            {item.can.view && (
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={RawMaterialController.show.url(
                                                            item.code
                                                        )}
                                                    >
                                                        Ver
                                                    </Link>
                                                </Button>
                                            )}

                                            {item.can.update && (
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={RawMaterialController.edit.url(
                                                            item.code
                                                        )}
                                                    >
                                                        Editar
                                                    </Link>
                                                </Button>
                                            )}

                                            {item.can.delete && (
                                                <Button
                                                    type="button"
                                                    variant="destructive"
                                                    size="sm"
                                                    onClick={() =>
                                                        handleDelete(item.code)
                                                    }
                                                >
                                                    Eliminar
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}

                            {/* Estado vacío */}
                            {rawMaterials.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="p-10 text-center text-sm text-muted-foreground"
                                    >
                                        No hay materias primas registradas.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Paginación */}
                <div className="flex flex-wrap gap-2">
                    {paginationLinks.map((link, index) => (
                        <Button
                            key={`${link.label}-${index}`}
                            type="button"
                            size="sm"
                            variant={link.active ? 'default' : 'outline'}
                            disabled={!link.url}
                            onClick={() => {
                                if (link.url) {
                                    router.visit(link.url, {
                                        preserveScroll: true,
                                        preserveState: true,
                                    });
                                }
                            }}
                            dangerouslySetInnerHTML={{
                                __html: link.label,
                            }}
                        />
                    ))}
                </div>
            </div>
        </>
    );
}