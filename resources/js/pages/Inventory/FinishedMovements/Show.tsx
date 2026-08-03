import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import {
    FinishedMovementReasonBadge,
    FinishedMovementTypeBadge,
} from '@/components/finished-inventory/finished-movement-badges';
import { FormattedDate } from '@/components/formatted-date';
import { FormattedNumber } from '@/components/formatted-number';
import { Button } from '@/components/ui/button';
import { index as finishedMovementsIndex } from '@/routes/finished-inventory-movements';
import type { FinishedInventoryMovement } from '@/types/finished-inventory';

type Props = {
    movement: FinishedInventoryMovement;
};

function DetailItem({
    label,
    value,
}: {
    label: string;
    value: React.ReactNode;
}) {
    return (
        <div className="space-y-1">
            <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </dt>
            <dd className="text-sm text-foreground">{value}</dd>
        </div>
    );
}

export default function FinishedMovementsShow({ movement }: Props) {
    return (
        <>
            <Head title={`Movimiento PT #${movement.id}`} />

            <div className="space-y-5 p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div className="space-y-2">
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={finishedMovementsIndex.url()}>
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Movimientos PT
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-semibold text-foreground">
                                Movimiento PT #{movement.id}
                            </h1>
                            <div className="mt-2 flex flex-wrap items-center gap-2">
                                <FinishedMovementTypeBadge
                                    type={movement.type}
                                />
                                <FinishedMovementReasonBadge
                                    reason={movement.reason}
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <dl className="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
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
                                    maxDecimals={2}
                                />
                            }
                        />
                        <DetailItem
                            label="Costo"
                            value={
                                movement.cost_price ? (
                                    <FormattedNumber
                                        value={movement.cost_price}
                                        maxDecimals={2}
                                    />
                                ) : (
                                    '-'
                                )
                            }
                        />
                        <DetailItem
                            label="Lote"
                            value={
                                movement.batch
                                    ? `#${movement.batch.id}`
                                    : 'Sin lote'
                            }
                        />
                        <DetailItem
                            label="Producto"
                            value={
                                <>
                                    <div>{movement.product?.name ?? '-'}</div>
                                    <div className="text-xs text-muted-foreground">
                                        {movement.product?.code ?? '-'}
                                    </div>
                                </>
                            }
                        />
                        <DetailItem
                            label="Presentación"
                            value={
                                movement.product_variant?.presentation_label ??
                                movement.product_variant?.name ??
                                '-'
                            }
                        />
                        <DetailItem
                            label="Bodega"
                            value={
                                <>
                                    <div>{movement.warehouse?.name ?? '-'}</div>
                                    <div className="text-xs text-muted-foreground">
                                        {movement.warehouse?.city ?? '-'}
                                    </div>
                                </>
                            }
                        />
                        <DetailItem
                            label="Creado por"
                            value={movement.created_by?.name ?? '-'}
                        />
                        <DetailItem
                            label="Orden producción"
                            value={
                                movement.production_order?.order_number ?? '-'
                            }
                        />
                        <div className="space-y-1 md:col-span-2 xl:col-span-3">
                            <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                Notas
                            </dt>
                            <dd className="text-sm whitespace-pre-wrap text-foreground">
                                {movement.notes ?? '-'}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </>
    );
}
