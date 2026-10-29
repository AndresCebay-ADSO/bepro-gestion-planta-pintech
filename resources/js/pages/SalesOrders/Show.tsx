import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, FileText, ShoppingCart } from 'lucide-react';

import { DetailTable } from '@/components/detail-table';
import { FormattedNumber } from '@/components/formatted-number';
import { PageHeader } from '@/components/page-header';
import { PriorityBadge } from '@/components/priority-badge';
import AdminOrderSidebar from '@/components/sales-orders/AdminOrderSidebar';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { quotationName } from '@/lib/quotation-name';
import { show as quotationsShow } from '@/routes/quotations';
import { index as salesOrdersIndex } from '@/routes/sales-orders';

type ProductItem = {
    id: number;
    product: { id: number; name: string };
    product_variant: {
        id: number;
        name: string;
        presentation_label: string | null;
    } | null;
    quantity: number;
    color: string | null;
};

type SalesOrderDetail = {
    id: number;
    client: {
        id: number;
        business_name: string;
        nit: string | null;
        contact_name: string | null;
        phone: string | null;
    };
    status: string;
    status_label: string;
    priority: string;
    priority_label: string;
    required_date: string | null;
    estimated_delivery_date: string | null;
    notes: string | null;
    shipping_address: string | null;
    quotation: { id: number; quotation_number: number | null } | null;
    items: ProductItem[];
    created_at: string;
    created_on: string | null;
    creator: { name: string } | null;
};

type StatusTransition = {
    value: string;
    label: string;
};

type Props = {
    order: SalesOrderDetail;
    statusTransitions: StatusTransition[];
    can: {
        update: boolean;
        updateStatus: boolean;
        viewQuotation: boolean;
    };
};

export default function SalesOrdersShow({
    order,
    statusTransitions,
    can,
}: Props) {
    return (
        <>
            <Head title={`Pedido #${order.id}`} />

            <div className="mx-auto max-w-5xl space-y-6 p-6">
                <div className="space-y-4">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={salesOrdersIndex().url}>
                            <ArrowLeft className="mr-1 h-4 w-4" />
                            Volver
                        </Link>
                    </Button>
                    <PageHeader
                        title={`Pedido #${order.id}`}
                        badge={
                            <div className="flex flex-wrap items-center gap-2">
                                <PriorityBadge value={order.priority} />
                                <StatusBadge
                                    enum="SalesOrderStatus"
                                    value={order.status}
                                />
                            </div>
                        }
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* min-w-0: sin él, la columna de la grilla se ensancha con la tabla y esta no se desplaza. */}
                    <div className="min-w-0 space-y-6 lg:col-span-2">
                        <div className="space-y-4 rounded-lg border border-border bg-card p-6">
                            <h2 className="text-lg font-semibold">
                                Información del cliente
                            </h2>
                            <div className="space-y-2 text-sm">
                                <p>
                                    <span className="text-muted-foreground">
                                        Razón social:
                                    </span>{' '}
                                    <span className="font-medium">
                                        {order.client.business_name}
                                    </span>
                                </p>
                                {order.client.nit && (
                                    <p>
                                        <span className="text-muted-foreground">
                                            NIT:
                                        </span>{' '}
                                        {order.client.nit}
                                    </p>
                                )}
                                <p>
                                    <span className="text-muted-foreground">
                                        Contacto:
                                    </span>{' '}
                                    {order.client.contact_name ?? '-'}
                                </p>
                                <p>
                                    <span className="text-muted-foreground">
                                        Teléfono:
                                    </span>{' '}
                                    {order.client.phone ?? '-'}
                                </p>
                                {order.estimated_delivery_date && (
                                    <p>
                                        <span className="text-muted-foreground">
                                            Fecha estimada de entrega:
                                        </span>{' '}
                                        <span className="font-medium">
                                            {order.estimated_delivery_date}
                                        </span>
                                    </p>
                                )}
                                {order.quotation && can.viewQuotation && (
                                    <p>
                                        <span className="text-muted-foreground">
                                            Origen:
                                        </span>{' '}
                                        <Button
                                            variant="link"
                                            size="sm"
                                            asChild
                                            className="h-auto p-0"
                                        >
                                            <Link
                                                href={
                                                    quotationsShow(
                                                        order.quotation.id,
                                                    ).url
                                                }
                                            >
                                                <FileText className="mr-1 h-3 w-3" />
                                                {quotationName(order.quotation)}
                                            </Link>
                                        </Button>
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="space-y-4 rounded-lg border border-border bg-card p-6">
                            <h2 className="flex items-center gap-2 text-lg font-semibold">
                                <ShoppingCart className="h-5 w-5" />
                                Productos solicitados
                            </h2>

                            <DetailTable
                                rows={order.items}
                                rowKey={(item) => item.id}
                                caption="Productos solicitados"
                                columns={[
                                    {
                                        key: 'product',
                                        header: 'Producto',
                                        cell: (item) => item.product.name,
                                    },
                                    {
                                        key: 'presentation',
                                        header: 'Presentación',
                                        cell: (item) =>
                                            item.product_variant
                                                ?.presentation_label ??
                                            item.product_variant?.name ??
                                            'Base',
                                        className: 'text-muted-foreground',
                                    },
                                    {
                                        key: 'color',
                                        header: 'Color',
                                        cell: (item) => item.color,
                                        className: 'text-muted-foreground',
                                    },
                                    {
                                        key: 'quantity',
                                        header: 'Cantidad',
                                        cell: (item) => (
                                            <FormattedNumber
                                                value={item.quantity}
                                                maxDecimals={0}
                                            />
                                        ),
                                        align: 'right',
                                        className: 'font-medium',
                                    },
                                ]}
                                empty="No hay productos en este pedido."
                            />
                        </div>

                        {order.notes && (
                            <div className="rounded-lg border border-border bg-card p-6">
                                <h2 className="mb-2 text-lg font-semibold">
                                    Notas
                                </h2>
                                <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                                    {order.notes}
                                </p>
                            </div>
                        )}
                    </div>

                    {(can.update || can.updateStatus) && (
                        <AdminOrderSidebar
                            order={order}
                            statusTransitions={statusTransitions}
                            can={can}
                        />
                    )}
                </div>
            </div>
        </>
    );
}
