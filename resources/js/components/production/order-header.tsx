import { CheckCircle2, FileSpreadsheet, FileText, Send } from 'lucide-react';

import {
    exportExcel as productionOrderExportExcel,
    exportPdf as productionOrderExportPdf,
} from '@/actions/App/Http/Controllers/ProductionOrderController';
import { FormattedDate } from '@/components/formatted-date';

import { FormattedNumber } from '@/components/formatted-number';
import { Badge } from '@/components/ui/badge';
import type {
    ProductionOrder,
    ProductionOrderStatus,
} from '@/types/production-orders';

type OrderHeaderProps = {
    order: ProductionOrder;
};

function statusLabel(status: ProductionOrderStatus): string {
    switch (status) {
        case 'pending':
            return 'Pendiente';
        case 'in_progress':
            return 'En Proceso';
        case 'pending_review':
            return 'Pendiente de Revisión';
        case 'completed':
            return 'Completada';
        case 'cancelled':
            return 'Cancelada';
    }
}

function statusVariant(
    status: ProductionOrderStatus,
): 'default' | 'secondary' | 'outline' | 'destructive' {
    switch (status) {
        case 'completed':
            return 'default';
        case 'pending_review':
            return 'outline';
        case 'cancelled':
            return 'destructive';
        default:
            return 'secondary';
    }
}

export function OrderHeader({ order }: OrderHeaderProps) {
    return (
        <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <div className="flex items-center gap-2">
                    <h1 className="text-3xl font-bold tracking-tight text-foreground">
                        Orden {order.order_number}{' '}
                        {order.lot_number && `(Lote ${order.lot_number})`}
                    </h1>
                    <Badge variant={statusVariant(order.status)}>
                        {statusLabel(order.status)}
                    </Badge>
                </div>
                <p className="mt-1 text-muted-foreground">
                    {order.product?.name} • Planta Cali •{' '}
                    <FormattedNumber value={order.quantity} maxDecimals={2} />{' '}
                    gal Proyectados
                </p>
                {order.status === 'pending_review' && order.submitted_by && (
                    <p className="mt-2 flex items-center gap-1 text-sm text-blue-700 dark:text-blue-300">
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
                    <p className="mt-2 text-sm text-amber-700 dark:text-amber-300">
                        Devuelta a planta: {order.rejection_reason}
                    </p>
                )}
            </div>
            <div className="flex items-center gap-2">
                {order.status === 'completed' && order.completion_date && (
                    <div className="mr-4 flex items-center gap-2 font-medium text-green-600">
                        <CheckCircle2 className="h-5 w-5" />
                        Finalizada el{' '}
                        <FormattedDate
                            value={order.completion_date}
                            format="short"
                        />
                    </div>
                )}
                <a
                    href={productionOrderExportPdf.url(order.id)}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-1.5 rounded-md border border-input bg-background px-3 py-2 text-sm font-medium shadow-xs transition-colors hover:bg-accent hover:text-accent-foreground"
                >
                    <FileText className="h-4 w-4" />
                    PDF
                </a>
                <a
                    href={productionOrderExportExcel.url(order.id)}
                    className="inline-flex items-center gap-1.5 rounded-md border border-input bg-background px-3 py-2 text-sm font-medium shadow-xs transition-colors hover:bg-accent hover:text-accent-foreground"
                >
                    <FileSpreadsheet className="h-4 w-4" />
                    Excel
                </a>
            </div>
        </div>
    );
}
