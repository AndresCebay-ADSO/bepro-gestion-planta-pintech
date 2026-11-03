import { Head } from '@inertiajs/react';

import { DetailItem, DetailList } from '@/components/detail-list';
import { FormattedDate } from '@/components/formatted-date';
import { FormattedNumber } from '@/components/formatted-number';
import { MovementDirectionBadge } from '@/components/movement-direction-badge';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Card, CardContent } from '@/components/ui/card';
import { finishedLotLabel } from '@/lib/finished-lot';
import {
    index as finishedMovementsIndex,
    show as finishedMovementsShow,
} from '@/routes/finished-inventory-movements';
import type { FinishedInventoryMovement } from '@/types/finished-inventory';

type Props = {
    movement: FinishedInventoryMovement;
    can: {
        viewCosts: boolean;
    };
};

export default function FinishedMovementsShow({ movement, can }: Props) {
    return (
        <>
            <Head title={`Movimiento PT #${movement.id}`} />

            <div className="space-y-6 p-6">
                <PageHeader
                    title={`Movimiento PT #${movement.id}`}
                    badge={
                        <div className="flex flex-wrap items-center gap-2">
                            <MovementDirectionBadge type={movement.type} />
                            <StatusBadge
                                enum="FinishedInventoryMovementReason"
                                value={movement.reason}
                            />
                        </div>
                    }
                />

                <Card>
                    <CardContent>
                        <DetailList columns={4}>
                            <DetailItem
                                label="Fecha"
                                value={
                                    <FormattedDate
                                        value={movement.movement_date}
                                        format="date"
                                    />
                                }
                            />
                            <DetailItem
                                label="Cantidad"
                                value={
                                    <FormattedNumber
                                        value={movement.quantity}
                                        kind="units"
                                    />
                                }
                            />
                            {can.viewCosts && (
                                <DetailItem
                                    label="Costo"
                                    value={
                                        <FormattedNumber
                                            value={movement.cost_price}
                                            kind="cost"
                                        />
                                    }
                                />
                            )}
                            <DetailItem
                                label="Lote"
                                value={
                                    movement.batch
                                        ? finishedLotLabel(
                                              movement.batch.production_order
                                                  .lot_number,
                                              movement.batch.production_order
                                                  .color,
                                          )
                                        : 'Sin lote'
                                }
                                valueClassName={
                                    movement.batch ? 'font-mono' : undefined
                                }
                            />
                            <DetailItem
                                label="Producto"
                                value={movement.product?.name}
                            />
                            <DetailItem
                                label="Presentación"
                                value={
                                    movement.product_variant
                                        ?.presentation_label ??
                                    movement.product_variant?.name
                                }
                            />
                            <DetailItem
                                label="Bodega"
                                value={
                                    movement.warehouse && (
                                        <>
                                            <div>{movement.warehouse.name}</div>
                                            {movement.warehouse.city && (
                                                <div className="text-xs text-muted-foreground">
                                                    {movement.warehouse.city}
                                                </div>
                                            )}
                                        </>
                                    )
                                }
                            />
                            <DetailItem
                                label="Creado por"
                                value={movement.created_by?.name}
                            />
                            <DetailItem
                                label="Orden producción"
                                value={movement.production_order?.order_number}
                                valueClassName="font-mono"
                            />
                            <DetailItem
                                label="Notas"
                                value={movement.notes}
                                span="full"
                                valueClassName="whitespace-pre-wrap"
                            />
                        </DetailList>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

// Las migas son la única forma de volver al listado (DESIGN.md §10).
FinishedMovementsShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Movimientos de PT', href: finishedMovementsIndex() },
        {
            title: `Movimiento PT #${props.movement.id}`,
            href: finishedMovementsShow(props.movement.id),
        },
    ],
});
