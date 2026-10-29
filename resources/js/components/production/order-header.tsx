import { CheckCircle2, FileSpreadsheet, FileText, Send } from 'lucide-react';

import {
    exportExcel as productionOrderExportExcel,
    exportPdf as productionOrderExportPdf,
} from '@/actions/App/Http/Controllers/ProductionOrderController';
import { FormattedDate } from '@/components/formatted-date';

import { FormattedNumber } from '@/components/formatted-number';
import { PageHeader } from '@/components/page-header';
import { CancelOrderDialog } from '@/components/production/cancel-order-dialog';
import { OrderColorDialog } from '@/components/production/order-color-dialog';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import type { ProductionOrder } from '@/types/production-orders';

type OrderHeaderProps = {
    order: ProductionOrder;
    /** Corregir el color: quien puede crear órdenes, con la orden abierta (`can.updateColor`). */
    canUpdateColor?: boolean;
    /** Cancelar la orden: permiso y estado pendiente o en curso (`can.cancel`, D-OPX2). */
    canCancel?: boolean;
};

export function OrderHeader({
    order,
    canUpdateColor = false,
    canCancel = false,
}: OrderHeaderProps) {
    // Galones de saldo ya consumidos por la orden: no vuelven al inventario si se cancela (B66).
    const consumedRemnantGallons = (order.remnant_consumptions ?? []).reduce(
        (total, consumption) => total + Number(consumption.quantity_gallons),
        0,
    );

    return (
        <PageHeader
            title={`Orden ${order.order_number} (Lote ${order.lot_number})`}
            badge={
                <StatusBadge
                    enum="ProductionOrderStatus"
                    value={order.status}
                />
            }
            description={
                <span className="flex flex-wrap items-center gap-x-1">
                    <span>
                        {order.product_display_name ?? order.product?.name}
                    </span>
                    {canUpdateColor && (
                        <OrderColorDialog
                            orderId={order.id}
                            color={order.color ?? null}
                        />
                    )}
                    <span>
                        • {order.warehouse?.name ?? 'Sin bodega'} •{' '}
                        <FormattedNumber
                            value={order.quantity}
                            maxDecimals={2}
                        />{' '}
                        gal proyectados
                    </span>
                </span>
            }
            actions={
                <>
                    {canCancel && (
                        <CancelOrderDialog
                            orderId={order.id}
                            orderNumber={order.order_number}
                            consumedRemnantGallons={consumedRemnantGallons}
                        />
                    )}
                    <Button variant="outline" asChild>
                        <a
                            href={productionOrderExportPdf.url(order.id)}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <FileText className="size-4" />
                            Ver PDF
                        </a>
                    </Button>
                    <Button variant="outline" asChild>
                        <a href={productionOrderExportExcel.url(order.id)}>
                            <FileSpreadsheet className="size-4" />
                            Excel
                        </a>
                    </Button>
                </>
            }
        >
            {order.status === 'completed' && order.completion_date && (
                <p className="mt-2 flex items-center gap-1 text-sm font-medium text-success-text">
                    <CheckCircle2 className="size-4" />
                    Finalizada el{' '}
                    <FormattedDate
                        value={order.completion_date}
                        format="short"
                    />
                </p>
            )}
            {order.status === 'pending_review' && order.submitted_by && (
                <p className="mt-2 flex items-center gap-1 text-sm text-info-text">
                    <Send className="h-4 w-4" />
                    Enviada por {order.submitted_by.name}
                    {order.submitted_at && (
                        <>
                            {' el '}
                            <FormattedDate
                                value={order.submitted_at}
                                format="datetime"
                            />
                        </>
                    )}
                </p>
            )}
            {order.rejection_reason && (
                <p className="mt-2 text-sm text-warning-text">
                    Devuelta a planta: {order.rejection_reason}
                </p>
            )}
        </PageHeader>
    );
}
