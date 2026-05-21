import { format } from 'date-fns';
import { CheckCircle2, FileSpreadsheet, FileText } from 'lucide-react';

import {
    exportExcel as productionOrderExportExcel,
    exportPdf as productionOrderExportPdf,
} from '@/actions/App/Http/Controllers/ProductionOrderController';
import { FormattedNumber } from '@/components/formatted-number';
import { Badge } from '@/components/ui/badge';
import type { ProductionOrder } from '@/types/production-orders';

type OrderHeaderProps = {
    order: ProductionOrder;
    isCompleted: boolean;
};

export function OrderHeader({ order, isCompleted }: OrderHeaderProps) {
    return (
        <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <div className="flex items-center gap-2">
                    <h1 className="text-3xl font-bold tracking-tight text-foreground">
                        Orden {order.order_number}
                    </h1>
                    <Badge variant={isCompleted ? 'default' : 'secondary'}>
                        {isCompleted ? 'Completada' : 'En Proceso'}
                    </Badge>
                </div>
                <p className="mt-1 text-muted-foreground">
                    {order.product?.name} • Planta Cali •{' '}
                    <FormattedNumber value={order.quantity} maxDecimals={2} />{' '}
                    gal Proyectados
                </p>
            </div>
            <div className="flex items-center gap-2">
                {isCompleted && order.completion_date && (
                    <div className="mr-4 flex items-center gap-2 font-medium text-green-600">
                        <CheckCircle2 className="h-5 w-5" />
                        Finalizada el{' '}
                        {format(
                            new Date(order.completion_date),
                            'dd/MM/yyyy HH:mm',
                        )}
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
