import { Head, Link, router } from '@inertiajs/react';

import {
    activate as formulasActivate,
    destroy as formulasDestroy,
    edit as formulasEdit,
} from '@/actions/App/Http/Controllers/FormulaController';
import { FormattedNumber } from '@/components/formatted-number';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index as formulasIndex } from '@/routes/formulas';
import { show as productsShow } from '@/routes/products';

type DetailItem = {
    id: number;
    step_order: number;
    quantity: string;
    raw_material?: { id: number; code: string } | null;
    unit_of_measure?: { name: string; symbol: string } | null;
};

type Props = {
    formula: {
        id: number;
        version: number;
        is_active: boolean;
        notes: string | null;
        created_at: string;
        product?: {
            id: number;
            code: string;
            name: string;
            unit_of_measure?: { name: string; symbol: string } | null;
        } | null;
        details: DetailItem[];
        created_by?: { name: string } | null;
        has_production_orders: boolean;
    };
    can: { update: boolean; delete: boolean };
};

export default function FormulasShow({ formula, can }: Props) {
    const handleDelete = () => {
        if (
            !window.confirm(
                `¿Eliminar la fórmula v${formula.version} de ${formula.product?.code}?`,
            )
        ) {
            return;
        }

        router.delete(formulasDestroy(formula.id));
    };

    const handleActivate = () => {
        router.post(formulasActivate(formula.id));
    };

    return (
        <>
            <Head
                title={`Fórmula v${formula.version} — ${formula.product?.code}`}
            />
            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Link
                                href={formulasIndex().url}
                                className="hover:text-foreground"
                            >
                                Fórmulas
                            </Link>
                            <span>/</span>
                            {formula.product && (
                                <>
                                    <Link
                                        href={
                                            productsShow({
                                                product: formula.product.id,
                                            }).url
                                        }
                                        className="font-mono hover:text-foreground"
                                    >
                                        {formula.product.code}
                                    </Link>
                                    <span>/</span>
                                </>
                            )}
                            <span>v{formula.version}</span>
                        </div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold text-foreground">
                                Fórmula v{formula.version}
                            </h1>
                            <Badge
                                variant={
                                    formula.is_active ? 'default' : 'secondary'
                                }
                            >
                                {formula.is_active
                                    ? 'Versión activa'
                                    : 'Inactiva'}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Producto:{' '}
                            <span className="font-mono font-medium text-foreground">
                                {formula.product?.code}
                            </span>{' '}
                            — {formula.product?.name}
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={formulasIndex().url}>Volver</Link>
                        </Button>
                        {can.update && !formula.has_production_orders && (
                            <Button variant="outline" asChild>
                                <Link href={formulasEdit(formula.id).url}>
                                    Editar fórmula
                                </Link>
                            </Button>
                        )}
                        {!formula.is_active && can.update && (
                            <Button variant="outline" onClick={handleActivate}>
                                Activar esta versión
                            </Button>
                        )}
                        {can.delete && (
                            <Button
                                variant="destructive"
                                onClick={handleDelete}
                            >
                                Eliminar
                            </Button>
                        )}
                    </div>
                </div>

                {/* Metadata */}
                <div className="grid gap-4 rounded-lg border border-border bg-card p-6 md:grid-cols-3">
                    <div>
                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                            Creada por
                        </p>
                        <p className="text-sm text-foreground">
                            {formula.created_by?.name ?? '-'}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                            Fecha
                        </p>
                        <p className="text-sm text-foreground">
                            {formula.created_at}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                            Notas
                        </p>
                        <p className="text-sm text-foreground">
                            {formula.notes ?? '—'}
                        </p>
                    </div>
                </div>

                {formula.has_production_orders && (
                    <div className="rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-900">
                        Esta fórmula ya fue usada en órdenes de producción. Por
                        seguridad del histórico, ya no se puede editar.
                    </div>
                )}

                {/* Ingredientes */}
                <div className="rounded-lg border border-border bg-card">
                    <div className="border-b border-border px-6 py-4">
                        <h2 className="font-medium text-foreground">
                            Ingredientes
                        </h2>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            Materias primas necesarias para producir una unidad
                            de{' '}
                            <span className="font-mono">
                                {formula.product?.code}
                            </span>
                            {formula.product?.unit_of_measure
                                ? ` (1 ${formula.product.unit_of_measure.symbol})`
                                : ''}
                        </p>
                    </div>

                    <table className="w-full text-sm">
                        <thead className="border-b border-border bg-muted/40">
                            <tr>
                                <th className="w-14 p-4 text-center font-medium">
                                    Paso
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Código MP
                                </th>
                                <th className="p-4 text-right font-medium">
                                    Cantidad
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Unidad
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {formula.details.map((detail) => (
                                <tr
                                    key={detail.id}
                                    className="border-b border-border/60 transition-colors last:border-0 hover:bg-muted/30"
                                >
                                    <td className="p-4 text-center tabular-nums text-muted-foreground">
                                        {detail.step_order ?? '—'}
                                    </td>
                                    <td className="p-4 font-mono font-medium text-foreground">
                                        {detail.raw_material?.code ?? '-'}
                                    </td>
                                    <td className="p-4 text-right text-foreground tabular-nums">
                                        <FormattedNumber value={detail.quantity} maxDecimals={2} />
                                    </td>
                                    <td className="p-4 text-muted-foreground">
                                        {detail.unit_of_measure
                                            ? `${detail.unit_of_measure.name} (${detail.unit_of_measure.symbol})`
                                            : '-'}
                                    </td>
                                </tr>
                            ))}
                            {formula.details.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="p-8 text-center text-sm text-muted-foreground"
                                    >
                                        Esta fórmula no tiene ingredientes
                                        registrados.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
