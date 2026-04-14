import { Head, Link, useForm, router } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { TableActions } from '@/components/table-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Pagination from '@/components/ui/pagination';
import {
    index as formulasIndex,
    create as formulasCreate,
    show as formulasShow,
} from '@/routes/formulas';
import type { PaginationLink } from '@/types/ui';

type FormulaItem = {
    id: number;
    version: number;
    is_active: boolean;
    notes: string | null;
    created_at: string;
    product?: { id: number; code: string; name: string } | null;
    created_by?: { name: string } | null;
};

type Props = {
    formulas: {
        data: FormulaItem[];
        links: PaginationLink[];
    };
    filters: { search?: string; product_id?: string };
    can: { create: boolean };
};

export default function FormulasIndex({ formulas, filters, can }: Props) {
    const { data, setData, get } = useForm({
        search: filters.search ?? '',
    });

    const handleSearch = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        get(formulasIndex().url, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <>
            <Head title="Fórmulas" />
            <div className="space-y-4 p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
                            Fórmulas
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Registro de fórmulas por producto. Cada versión
                            activa define los ingredientes usados en producción.
                        </p>
                    </div>
                    {can.create && (
                        <Button asChild>
                            <Link href={formulasCreate().url}>
                                Nueva Fórmula
                            </Link>
                        </Button>
                    )}
                </div>

                <form
                    onSubmit={handleSearch}
                    className="relative w-full max-w-sm"
                >
                    <Input
                        placeholder="Buscar por código o nombre de producto…"
                        value={data.search}
                        onChange={(e) => setData('search', e.target.value)}
                        className="pr-4"
                    />
                </form>

                <div className="rounded-xl border border-border bg-card shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b border-border bg-muted/50">
                            <tr>
                                <th className="p-4 text-left font-medium">
                                    Producto
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Versión
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Estado
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Notas
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Creada
                                </th>
                                <th className="p-4 text-right font-medium">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {formulas.data.map((formula) => (
                                <tr
                                    key={formula.id}
                                    className="border-b border-border/50 transition-colors hover:bg-muted/30"
                                >
                                    <td className="p-4">
                                        <div className="font-mono font-medium text-foreground">
                                            {formula.product?.code ?? '-'}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {formula.product?.name ?? '-'}
                                        </div>
                                    </td>
                                    <td className="p-4 font-mono">
                                        v{formula.version}
                                    </td>
                                    <td className="p-4">
                                        <Badge
                                            variant={
                                                formula.is_active
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {formula.is_active
                                                ? 'Activa'
                                                : 'Inactiva'}
                                        </Badge>
                                    </td>
                                    <td className="max-w-xs truncate p-4 text-muted-foreground">
                                        {formula.notes ?? '-'}
                                    </td>
                                    <td className="p-4 text-xs text-muted-foreground">
                                        {formula.created_at}
                                    </td>
                                    <td className="p-4 text-right">
                                        <TableActions
                                            actions={{
                                                view: true,
                                                edit: false,
                                                delete: true,
                                            }}
                                            onView={() =>
                                                router.get(
                                                    formulasShow({
                                                        formula: formula.id,
                                                    }).url,
                                                )
                                            }
                                            onDelete={() => {
                                                if (
                                                    window.confirm(
                                                        `¿Eliminar la fórmula v${formula.version} de ${formula.product?.code}?`,
                                                    )
                                                ) {
                                                    router.delete(
                                                        `/formulas/${formula.id}`,
                                                    );
                                                }
                                            }}
                                        />
                                    </td>
                                </tr>
                            ))}
                            {formulas.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="p-8 text-center text-sm text-muted-foreground"
                                    >
                                        No hay fórmulas registradas.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="mt-4 flex justify-center">
                    <Pagination links={formulas.links} />
                </div>
            </div>
        </>
    );
}
