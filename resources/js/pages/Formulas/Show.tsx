import { Head, Link, router } from '@inertiajs/react';
import { BadgeDollarSign } from 'lucide-react';

import {
    activate as formulasActivate,
    deactivate as formulasDeactivate,
    destroy as formulasDestroy,
    edit as formulasEdit,
    priceReference as formulasPriceReference,
} from '@/actions/App/Http/Controllers/FormulaController';
import { DetailTable } from '@/components/detail-table';
import { FormattedDate } from '@/components/formatted-date';
import { FormattedNumber } from '@/components/formatted-number';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/hooks/use-confirm';
import { formulaCrumb } from '@/lib/formula-name';
import { breadcrumbHref } from '@/lib/navigation';
import {
    index as formulasIndex,
    show as formulasShow,
} from '@/routes/formulas';

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

export default function FormulasShow({ formula, can }: Props) {
    const confirm = useConfirm();

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
                <PageHeader
                    title={title}
                    description={
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
                <section className="space-y-4">
                    <div>
                        <h2 className="text-lg font-semibold">Ingredientes</h2>
                        <p className="mt-0.5 text-sm text-muted-foreground">
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

                    <DetailTable
                        rows={formula.details}
                        rowKey={(detail) => detail.id}
                        caption="Ingredientes"
                        columns={[
                            {
                                key: 'step',
                                header: 'Paso',
                                cell: (detail) => (
                                    <FormattedNumber
                                        value={detail.step_order}
                                        maxDecimals={0}
                                    />
                                ),
                                align: 'center',
                                className: 'w-14 text-muted-foreground',
                            },
                            {
                                key: 'code',
                                header: 'Código MP',
                                cell: (detail) =>
                                    detail.raw_material?.code ?? null,
                                className: 'font-mono font-medium',
                            },
                            {
                                // La unidad va con la cifra («12,5 kg»): sin columna aparte, la tabla cabe a 390 px.
                                key: 'quantity',
                                header: 'Cantidad',
                                cell: (detail) => (
                                    <>
                                        <FormattedNumber
                                            value={detail.quantity}
                                            maxDecimals={4}
                                        />
                                        {detail.unit_of_measure &&
                                            ` ${detail.unit_of_measure.symbol}`}
                                    </>
                                ),
                                align: 'right',
                            },
                        ]}
                        empty="Esta fórmula no tiene ingredientes registrados."
                    />
                </section>
            </div>
        </>
    );
}

// Las migas son la única forma de volver al listado (DESIGN.md §10); el listado conserva sus filtros con `returnTo`.
FormulasShow.layout = (props: Props) => ({
    breadcrumbs: [
        {
            title: 'Fórmulas',
            href: breadcrumbHref(props.returnTo, formulasIndex().url),
        },
        {
            title: formulaCrumb(props.formula),
            href: formulasShow({ formula: props.formula.id }),
        },
    ],
});
