import { Head } from '@inertiajs/react';

import { DetailItem, DetailList } from '@/components/detail-list';
import { FormattedDate } from '@/components/formatted-date';
import { FormattedNumber } from '@/components/formatted-number';
import { MovementDirectionBadge } from '@/components/movement-direction-badge';
import { PageHeader } from '@/components/page-header';
import { Card, CardContent } from '@/components/ui/card';
import {
    index as inventoryMovementsIndex,
    show as inventoryMovementsShow,
} from '@/routes/inventory-movements';

type Props = {
    movement: {
        id: number;
        type: string;
        quantity: string;
        cost_price?: string | null;
        movement_date: string;
        notes: string | null;
        raw_material?: { code: string } | null;
        batch?: {
            lot_number: string | null;
            supplier?: { name: string } | null;
        } | null;
        warehouse?: { name: string; city: string | null } | null;
        production_order?: { order_number: string } | null;
        created_by?: { name: string } | null;
    };
    can: {
        viewCosts: boolean;
    };
};

export default function InventoryMovementsShow({ movement, can }: Props) {
    return (
        <>
            <Head title={`Movimiento #${movement.id}`} />
            <div className="space-y-6 p-6">
                <PageHeader
                    title={`Movimiento #${movement.id}`}
                    badge={<MovementDirectionBadge type={movement.type} />}
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
                                        kind="quantity"
                                    />
                                }
                            />
                            {can.viewCosts && (
                                <DetailItem
                                    label="Costo"
                                    value={
                                        <FormattedNumber
                                            value={movement.cost_price}
                                            kind="unitCost"
                                        />
                                    }
                                />
                            )}
                            <DetailItem
                                label="Materia prima"
                                value={movement.raw_material?.code}
                                valueClassName="font-mono"
                            />
                            <DetailItem
                                label="Lote"
                                value={movement.batch?.lot_number}
                                valueClassName="font-mono"
                            />
                            <DetailItem
                                label="Proveedor"
                                value={movement.batch?.supplier?.name}
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
InventoryMovementsShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Movimientos de MP', href: inventoryMovementsIndex() },
        {
            title: `Movimiento #${props.movement.id}`,
            href: inventoryMovementsShow(props.movement.id),
        },
    ],
});
