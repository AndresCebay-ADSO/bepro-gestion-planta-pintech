import { Head, Link } from '@inertiajs/react';

import { DetailTable } from '@/components/detail-table';
import { FormattedDate } from '@/components/formatted-date';
import { FormattedNumber } from '@/components/formatted-number';
import { PageHeader } from '@/components/page-header';
import { Card, CardContent } from '@/components/ui/card';
import {
    index as shrinkWrapsIndex,
    show as shrinkWrapsShow,
} from '@/routes/production/shrink-wraps';
import { show as productionOrderShow } from '@/routes/production-orders';
import type { ShrinkWrapItemRow, ShrinkWrapRow } from '@/types';

type Props = {
    shrinkWrap: ShrinkWrapRow & {
        /** Día en que se descontó del inventario (puede ser posterior al termoencogido). */
        registered_on: string | null;
        notes: string | null;
        items: ShrinkWrapItemRow[];
    };
    can: {
        viewCosts: boolean;
        viewOrder: boolean;
    };
};

export default function ShrinkWrapsShow({ shrinkWrap, can }: Props) {
    const { order } = shrinkWrap;
    const orderLabel = `${order.order_number} · Lote ${order.lot_number}`;

    return (
        <>
            <Head title={`Termoencogido #${shrinkWrap.id}`} />

            <div className="space-y-6 p-6">
                <PageHeader
                    title={`Termoencogido #${shrinkWrap.id}`}
                    description="Registro cerrado: su consumo ya se descontó del inventario. Un error se corrige con un movimiento opuesto y una nota."
                />

                <Card>
                    <CardContent className="grid gap-4 pt-6 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Orden de producción
                            </p>
                            {can.viewOrder ? (
                                <Link
                                    href={productionOrderShow(order.id).url}
                                    className="font-medium text-primary hover:underline"
                                >
                                    {orderLabel}
                                </Link>
                            ) : (
                                <p className="font-medium">{orderLabel}</p>
                            )}
                            {order.product_name && (
                                <p className="text-xs text-muted-foreground">
                                    {order.product_name}
                                </p>
                            )}
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Tipo
                            </p>
                            <p className="font-medium">
                                {shrinkWrap.type_name}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Aplicaciones
                            </p>
                            <p className="font-medium">
                                {shrinkWrap.applications}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Termoencogido el
                            </p>
                            <p className="font-medium">
                                <FormattedDate
                                    value={shrinkWrap.wrapped_at}
                                    format="date"
                                />
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Descontado del inventario el
                            </p>
                            <p className="font-medium">
                                <FormattedDate
                                    value={shrinkWrap.registered_on}
                                    format="date"
                                />
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Bodega
                            </p>
                            <p className="font-medium">
                                {shrinkWrap.warehouse_name}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Registró
                            </p>
                            <p className="font-medium">
                                {shrinkWrap.created_by_name}
                            </p>
                        </div>
                        {can.viewCosts && (
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Costo (gasto general)
                                </p>
                                <p className="font-medium">
                                    <FormattedNumber
                                        value={shrinkWrap.total_cost}
                                        currency
                                        maxDecimals={2}
                                    />
                                </p>
                            </div>
                        )}
                        {shrinkWrap.notes && (
                            <div className="sm:col-span-2 lg:col-span-3">
                                <p className="text-xs text-muted-foreground">
                                    Notas
                                </p>
                                <p className="whitespace-pre-line">
                                    {shrinkWrap.notes}
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <section className="space-y-4">
                    <div>
                        <h2 className="text-lg font-semibold">
                            Empaque secundario descontado
                        </h2>
                        <p className="mt-0.5 text-sm text-muted-foreground">
                            La receta con que se registró: editar el tipo
                            después no la cambia.
                        </p>
                    </div>

                    <DetailTable
                        rows={shrinkWrap.items}
                        rowKey={(item) => item.id}
                        caption="Empaque secundario descontado"
                        columns={[
                            {
                                key: 'code',
                                header: 'Materia prima',
                                cell: (item) => item.code,
                                className: 'font-mono',
                            },
                            {
                                key: 'per_application',
                                header: 'Por aplicación',
                                cell: (item) => (
                                    <>
                                        <FormattedNumber
                                            value={
                                                item.quantity_per_application
                                            }
                                            maxDecimals={4}
                                        />
                                        {item.unit_symbol &&
                                            ` ${item.unit_symbol}`}
                                    </>
                                ),
                                align: 'right',
                            },
                            {
                                key: 'quantity',
                                header: 'Total',
                                cell: (item) => (
                                    <>
                                        <FormattedNumber
                                            value={item.quantity}
                                            maxDecimals={4}
                                        />
                                        {item.unit_symbol &&
                                            ` ${item.unit_symbol}`}
                                    </>
                                ),
                                align: 'right',
                                className: 'font-medium',
                            },
                            ...(can.viewCosts
                                ? [
                                      {
                                          key: 'cost',
                                          header: 'Costo',
                                          cell: (item: ShrinkWrapItemRow) => (
                                              <FormattedNumber
                                                  value={item.total_cost}
                                                  currency
                                                  maxDecimals={2}
                                              />
                                          ),
                                          align: 'right' as const,
                                      },
                                  ]
                                : []),
                        ]}
                        empty="Este registro no descontó empaque secundario."
                    />
                </section>
            </div>
        </>
    );
}

// Las migas son la única forma de volver al listado (DESIGN.md §10).
ShrinkWrapsShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Termoencogido', href: shrinkWrapsIndex() },
        {
            title: `Termoencogido #${props.shrinkWrap.id}`,
            href: shrinkWrapsShow(props.shrinkWrap.id),
        },
    ],
});
