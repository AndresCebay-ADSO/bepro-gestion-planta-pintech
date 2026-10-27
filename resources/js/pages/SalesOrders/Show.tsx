import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, FileText, ShoppingCart } from 'lucide-react';

import { PageHeader } from '@/components/page-header';
import { PriorityBadge } from '@/components/priority-badge';
import AdminOrderSidebar from '@/components/sales-orders/AdminOrderSidebar';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
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
    quotation_id: number | null;
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
                    <div className="space-y-6 lg:col-span-2">
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
                                {order.quotation_id && can.viewQuotation && (
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
                                                        order.quotation_id,
                                                    ).url
                                                }
                                            >
                                                <FileText className="mr-1 h-3 w-3" />
                                                Cotización #{order.quotation_id}
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

                            {order.items.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No hay productos en este pedido.
                                </p>
                            ) : (
                                <div className="overflow-hidden rounded border border-border">
                                    <table className="w-full text-sm">
                                        <thead className="border-b border-border bg-muted/50">
                                            <tr>
                                                <th className="p-3 text-left">
                                                    Producto
                                                </th>
                                                <th className="p-3 text-left">
                                                    Presentación
                                                </th>
                                                <th className="p-3 text-left">
                                                    Color
                                                </th>
                                                <th className="p-3 text-right">
                                                    Cantidad
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {order.items.map((item) => (
                                                <tr
                                                    key={item.id}
                                                    className="border-b border-border/50"
                                                >
                                                    <td className="p-3">
                                                        <span className="font-medium">
                                                            {item.product.name}
                                                        </span>
                                                    </td>
                                                    <td className="p-3 text-muted-foreground">
                                                        {item.product_variant
                                                            ?.presentation_label ??
                                                            item.product_variant
                                                                ?.name ??
                                                            'Base'}
                                                    </td>
                                                    <td className="p-3 text-muted-foreground">
                                                        {item.color ?? '—'}
                                                    </td>
                                                    <td className="p-3 text-right font-medium">
                                                        {item.quantity}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
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
