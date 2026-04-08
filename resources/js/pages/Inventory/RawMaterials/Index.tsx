import { Head, Link, router, usePage } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';
import { route } from 'ziggy-js';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

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

export default function RawMaterialsIndex({ rawMaterials, filters, can }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const flash = usePage<{ flash?: { success?: string; error?: string } }>().props.flash;

    const paginationLinks = useMemo(
        () => rawMaterials.links.filter((link) => link.label !== '&laquo; Previous' && link.label !== 'Next &raquo;'),
        [rawMaterials.links],
    );

    const handleSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        router.get(
            route('raw-materials.index'),
            { search },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const handleDelete = (code: string) => {
        if (!window.confirm('¿Estás seguro de eliminar esta materia prima?')) {
            return;
        }

        router.delete(route('raw-materials.destroy', code), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Materias Primas" />

            <div className="space-y-6 p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">Materias Primas</h1>
                        <p className="text-sm text-muted-foreground">Inventario base de materias primas de planta.</p>
                    </div>
                    {can.create && (
                        <Button asChild>
                            <Link href={route('raw-materials.create')}>Nueva Materia Prima</Link>
                        </Button>
                    )}
                </div>

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

                <form onSubmit={handleSearch} className="flex flex-col gap-2 sm:flex-row">
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Buscar por código..."
                        className="sm:max-w-sm"
                    />
                    <Button type="submit" variant="outline">
                        Buscar
                    </Button>
                </form>

                <div className="overflow-x-auto rounded-lg border border-border bg-card">
                    <table className="w-full text-sm">
                        <thead className="border-b border-border bg-muted/40">
                            <tr>
                                <th className="p-3 text-left font-medium text-foreground">Código</th>
                                <th className="p-3 text-left font-medium text-foreground">Unidad</th>
                                <th className="p-3 text-left font-medium text-foreground">Precio Actual</th>
                                <th className="p-3 text-left font-medium text-foreground">Stock Mínimo</th>
                                <th className="p-3 text-left font-medium text-foreground">Estado</th>
                                <th className="p-3 text-right font-medium text-foreground">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rawMaterials.data.map((item) => (
                                <tr key={item.id} className="border-b border-border/60 last:border-0">
                                    <td className="p-3 font-medium text-foreground">{item.code}</td>
                                    <td className="p-3 text-muted-foreground">
                                        {item.unit_of_measure ? `${item.unit_of_measure.name} (${item.unit_of_measure.symbol})` : '-'}
                                    </td>
                                    <td className="p-3 text-muted-foreground">${item.current_price}</td>
                                    <td className="p-3 text-muted-foreground">{item.minimum_stock}</td>
                                    <td className="p-3">
                                        <span
                                            className={item.is_active
                                                ? 'rounded-full bg-emerald-500/15 px-2 py-1 text-xs font-medium text-emerald-600 dark:text-emerald-300'
                                                : 'rounded-full bg-slate-500/15 px-2 py-1 text-xs font-medium text-slate-600 dark:text-slate-300'}
                                        >
                                            {item.is_active ? 'Activa' : 'Inactiva'}
                                        </span>
                                    </td>
                                    <td className="p-3 text-right">
                                        <div className="flex justify-end gap-2">
                                            {item.can.view && (
                                                <Button asChild variant="outline" size="sm">
                                                    <Link href={route('raw-materials.show', item.code)}>Ver</Link>
                                                </Button>
                                            )}
                                            {item.can.update && (
                                                <Button asChild variant="outline" size="sm">
                                                    <Link href={route('raw-materials.edit', item.code)}>Editar</Link>
                                                </Button>
                                            )}
                                            {item.can.delete && (
                                                <Button type="button" variant="destructive" size="sm" onClick={() => handleDelete(item.code)}>
                                                    Eliminar
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}

                            {rawMaterials.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="p-8 text-center text-sm text-muted-foreground">
                                        No se encontraron materias primas.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap gap-1">
                    {paginationLinks.map((link, index) => (
                        <Button
                            key={`${link.label}-${index}`}
                            type="button"
                            size="sm"
                            variant={link.active ? 'default' : 'outline'}
                            disabled={!link.url}
                            onClick={() => {
                                if (link.url) {
                                    router.visit(link.url, { preserveScroll: true, preserveState: true });
                                }
                            }}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            </div>
        </>
    );
}
