import { Head } from '@inertiajs/react';

import { DetailItem, DetailList } from '@/components/detail-list';
import { DetailTable } from '@/components/detail-table';
import { FormattedDate } from '@/components/formatted-date';
import { FormattedNumber } from '@/components/formatted-number';
import { PageHeader } from '@/components/page-header';
import { RecordLink } from '@/components/record-link';
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
                    <CardContent>
                        <DetailList columns={3}>
                            <DetailItem
                                label="Orden de producción"
                                value={
                                    <>
                                        <span className="font-mono">
                                            {can.viewOrder ? (
                                                <RecordLink
                                                    href={
                                                        productionOrderShow(
                                                            order.id,
                                                        ).url
                                                    }
                                                >
                                                    {orderLabel}
                                                </RecordLink>
                                            ) : (
                                                orderLabel
                                            )}
                                        </span>
                                        {order.product_name && (
                                            <div className="text-xs text-muted-foreground">
                                                {order.product_name}
                                            </div>
                                        )}
                                    </>
                                }
                            />
                            <DetailItem
                                label="Tipo"
                                value={shrinkWrap.type_name}
                            />
                            <DetailItem
                                label="Aplicaciones"
                                value={shrinkWrap.applications}
                            />
                            <DetailItem
                                label="Termoencogido el"
                                value={
                                    <FormattedDate
                                        value={shrinkWrap.wrapped_at}
                                        format="date"
                                    />
                                }
                            />
                            <DetailItem
                                label="Descontado del inventario el"
                                value={
                                    <FormattedDate
                                        value={shrinkWrap.registered_on}
                                        format="date"
                                    />
                                }
                            />
                            <DetailItem
                                label="Bodega"
                                value={shrinkWrap.warehouse_name}
                            />
                            <DetailItem
                                label="Registró"
                                value={shrinkWrap.created_by_name}
                            />
                            {can.viewCosts && (
                                <DetailItem
                                    label="Costo (gasto general)"
                                    value={
                                        <FormattedNumber
                                            value={shrinkWrap.total_cost}
                                            currency
                                            maxDecimals={2}
                                        />
                                    }
                                />
                            )}
                            {shrinkWrap.notes && (
                                <DetailItem
                                    label="Notas"
                                    value={shrinkWrap.notes}
                                    span="full"
                                    valueClassName="whitespace-pre-wrap"
                                />
                            )}
                        </DetailList>
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
