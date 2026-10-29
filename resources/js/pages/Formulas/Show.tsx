import { Head, Link, router } from '@inertiajs/react';
import { BadgeDollarSign } from 'lucide-react';

import {
    activate as formulasActivate,
    deactivate as formulasDeactivate,
    destroy as formulasDestroy,
    edit as formulasEdit,
    priceReference as formulasPriceReference,
} from '@/actions/App/Http/Controllers/FormulaController';
import { DetailPageHeader } from '@/components/detail-page-header';
import { FormattedDate } from '@/components/formatted-date';
import { FormattedNumber } from '@/components/formatted-number';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/hooks/use-confirm';
import { resolveModuleListHref } from '@/lib/navigation';
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
    returnTo?: string | null;
    formula: {
        id: number;
        version: number;
        name: string | null;
        is_active: boolean;
        is_price_reference: boolean;
        notes: string | null;
        created_at: string;
        product?: {
            id: number;
            name: string;
            unit_of_measure?: { name: string; symbol: string } | null;
        } | null;
        details: DetailItem[];
        created_by?: { name: string } | null;
        has_production_orders: boolean;
    };
    can: { update: boolean; activate: boolean; delete: boolean };
};

export default function FormulasShow({ returnTo, formula, can }: Props) {
    const confirm = useConfirm();
    const formulasListHref = resolveModuleListHref(
        returnTo,
        '/formulas',
        formulasIndex().url,
    );

    const handleDelete = async () => {
        if (
            !(await confirm({
                title: `¿Eliminar la fórmula v${formula.version} de ${formula.product?.name ?? 'fórmula'}?`,
                confirmLabel: 'Eliminar',
                destructive: true,
            }))
        ) {
            return;
        }

        router.delete(formulasDestroy(formula.id));
    };

    const handleActivate = () => {
        router.post(formulasActivate(formula.id));
    };

    const handleDeactivate = () => {
        router.post(formulasDeactivate(formula.id));
    };

    const handleUseForPrice = () => {
        router.post(formulasPriceReference(formula.id));
    };

    const title = formula.name
        ? `Fórmula v${formula.version} · ${formula.name}`
        : `Fórmula v${formula.version}`;

    return (
        <>
            <Head title={`${title} — ${formula.product?.name}`} />
            <div className="space-y-6 p-6">
                <DetailPageHeader
                    breadcrumbs={[
                        {
                            title: 'Fórmulas',
                            href: formulasListHref,
                        },
                        ...(formula.product
                            ? [
                                  {
                                      title: formula.product.name,
                                      href: productsShow({
                                          product: formula.product.id,
                                      }).url,
                                  },
                              ]
                            : []),
                        { title: `v${formula.version}`, href: '#' },
                    ]}
                    title={title}
                    subtitle={
                        <>
                            Producto:{' '}
                            <span className="font-medium text-foreground">
                                {formula.product?.name}
                            </span>
                        </>
                    }
                    badge={
                        <div className="flex flex-wrap gap-2">
                            <Badge
                                variant={
                                    formula.is_active ? 'default' : 'secondary'
                                }
                            >
                                {formula.is_active ? 'Activa' : 'Inactiva'}
                            </Badge>
                            {formula.is_price_reference && (
                                <Badge variant="outline">
                                    <BadgeDollarSign aria-hidden="true" />
                                    Referencia de precio
                                </Badge>
                            )}
                        </div>
                    }
                    returnTo={returnTo}
                    defaultReturnHref={formulasListHref}
                    defaultReturnLabel="Fórmulas"
                    actions={
                        <>
                            {can.update && !formula.has_production_orders && (
                                <Button variant="outline" asChild>
                                    <Link href={formulasEdit(formula.id).url}>
                                        Editar fórmula
                                    </Link>
                                </Button>
                            )}
                            {can.activate &&
                                formula.is_active &&
                                !formula.is_price_reference && (
                                    <Button
                                        variant="outline"
                                        onClick={handleUseForPrice}
                                    >
                                        Usar para el precio
                                    </Button>
                                )}
                            {can.activate && !formula.is_active && (
                                <Button
                                    variant="outline"
                                    onClick={handleActivate}
                                >
                                    Activar
                                </Button>
                            )}
                            {can.activate &&
                                formula.is_active &&
                                !formula.is_price_reference && (
                                    <Button
                                        variant="outline"
                                        onClick={handleDeactivate}
                                    >
                                        Desactivar
                                    </Button>
                                )}
                            {can.delete && !formula.is_price_reference && (
                                <Button
                                    variant="destructive"
                                    onClick={handleDelete}
                                >
                                    Eliminar
                                </Button>
                            )}
                        </>
                    }
                />

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
                            <FormattedDate
                                value={formula.created_at}
                                format="datetime"
                            />
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

                {formula.is_price_reference && (
                    <div className="rounded-lg border border-border bg-muted/40 px-4 py-3 text-sm text-muted-foreground">
                        El costo y el precio de lista del producto salen de esta
                        fórmula. Para desactivarla o eliminarla, marca antes
                        otra fórmula activa como referencia de precio con «Usar
                        para el precio».
                    </div>
                )}

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
                            <span className="font-medium">
                                {formula.product?.name}
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
                                    <td className="p-4 text-center text-muted-foreground tabular-nums">
                                        {detail.step_order ?? '—'}
                                    </td>
                                    <td className="p-4 font-mono font-medium text-foreground">
                                        {detail.raw_material?.code ?? '-'}
                                    </td>
                                    <td className="p-4 text-right text-foreground tabular-nums">
                                        <FormattedNumber
                                            value={detail.quantity}
                                            maxDecimals={4}
                                        />
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
