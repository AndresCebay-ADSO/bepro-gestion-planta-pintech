import { Head, Link } from '@inertiajs/react';

import { DetailPageHeader } from '@/components/detail-page-header';
import { FormattedDate } from '@/components/formatted-date';
import { FormattedNumber } from '@/components/formatted-number';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
                <DetailPageHeader
                    breadcrumbs={[
                        {
                            title: 'Termoencogido',
                            href: shrinkWrapsIndex().url,
                        },
                        {
                            title: `#${shrinkWrap.id}`,
                            href: shrinkWrapsShow(shrinkWrap.id).url,
                        },
                    ]}
                    title={`Termoencogido #${shrinkWrap.id}`}
                    subtitle="Registro cerrado: su consumo ya se descontó del inventario. Un error se corrige con un movimiento opuesto y una nota."
                    defaultReturnHref={shrinkWrapsIndex().url}
                    defaultReturnLabel="Termoencogido"
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

                <Card>
                    <CardHeader>
                        <CardTitle>Empaque secundario descontado</CardTitle>
                        <CardDescription>
                            La receta con que se registró: editar el tipo
                            después no la cambia.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto rounded-lg border border-border">
                            <table className="w-full text-sm">
                                <thead className="border-b border-border bg-muted/40">
                                    <tr>
                                        <th className="p-3 text-left font-medium">
                                            Materia prima
                                        </th>
                                        <th className="p-3 text-right font-medium">
                                            Por aplicación
                                        </th>
                                        <th className="p-3 text-right font-medium">
                                            Total
                                        </th>
                                        {can.viewCosts && (
                                            <th className="p-3 text-right font-medium">
                                                Costo
                                            </th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {shrinkWrap.items.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="border-b border-border/60 last:border-0"
                                        >
                                            <td className="p-3 font-mono">
                                                {item.code}
                                            </td>
                                            <td className="p-3 text-right">
                                                <FormattedNumber
                                                    value={
                                                        item.quantity_per_application
                                                    }
                                                    maxDecimals={4}
                                                />{' '}
                                                {item.unit_symbol}
                                            </td>
                                            <td className="p-3 text-right font-medium">
                                                <FormattedNumber
                                                    value={item.quantity}
                                                    maxDecimals={4}
                                                />{' '}
                                                {item.unit_symbol}
                                            </td>
                                            {can.viewCosts && (
                                                <td className="p-3 text-right">
                                                    <FormattedNumber
                                                        value={item.total_cost}
                                                        currency
                                                        maxDecimals={2}
                                                    />
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
