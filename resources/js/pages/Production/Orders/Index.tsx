import { Head, router } from '@inertiajs/react';
import { TableActions } from '@/components/table-actions';
import { Badge } from '@/components/ui/badge';
import Pagination from '@/components/ui/pagination';
import {
    show as productionOrderShow,
} from '@/routes/production-orders';
import type { PaginationLink } from '@/types/ui';

type ProductionOrderItem = {
    id: number;
    order_number: string;
    product?: { code: string; name: string } | null;
    formula?: { version: number } | null;
    warehouse?: { name: string } | null;
    quantity: string | number;
    status: 'pending' | 'in_progress' | 'completed' | 'cancelled';
    planned_date: string;
    completion_date: string | null;
    created_at: string;
};

type Props = {
    orders: {
        data: ProductionOrderItem[];
        links: PaginationLink[];
    };
};

export default function ProductionOrdersIndex({ orders }: Props) {
    const getStatusVariant = (status: ProductionOrderItem['status']) => {
        switch (status) {
            case 'pending': return 'secondary';
            case 'in_progress': return 'default';
            case 'completed': return 'default'; // In a real system, maybe success/green if available
            case 'cancelled': return 'destructive';
            default: return 'secondary';
        }
    };

    const getStatusLabel = (status: ProductionOrderItem['status']) => {
        switch (status) {
            case 'pending': return 'Pendiente';
            case 'in_progress': return 'En Proceso';
            case 'completed': return 'Completada';
            case 'cancelled': return 'Cancelada';
            default: return status;
        }
    };

    return (
        <>
            <Head title="Órdenes de Producción" />
            <div className="space-y-4 p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
                            Órdenes de Producción
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Gestión y seguimiento de la fabricación de pinturas en Planta Cali.
                        </p>
                    </div>
                </div>

                <div className="rounded-xl border border-border bg-card shadow-sm overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="border-b border-border bg-muted/50">
                            <tr>
                                <th className="p-4 text-left font-medium">Orden #</th>
                                <th className="p-4 text-left font-medium">Producto</th>
                                <th className="p-4 text-left font-medium">Planificado</th>
                                <th className="p-4 text-left font-medium">Estado</th>
                                <th className="p-4 text-left font-medium">Fecha Plan</th>
                                <th className="p-4 text-right font-medium">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {orders.data.map((order) => (
                                <tr
                                    key={order.id}
                                    className="border-b border-border/50 transition-colors hover:bg-muted/30"
                                >
                                    <td className="p-4 font-mono font-medium">
                                        {order.order_number}
                                    </td>
                                    <td className="p-4">
                                        <div className="font-medium text-foreground">
                                            {order.product?.name ?? 'S/N'}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {order.product?.code} (v{order.formula?.version})
                                        </div>
                                    </td>
                                    <td className="p-4">
                                        {order.quantity} L
                                    </td>
                                    <td className="p-4">
                                        <Badge variant={getStatusVariant(order.status)}>
                                            {getStatusLabel(order.status)}
                                        </Badge>
                                    </td>
                                    <td className="p-4 text-xs text-muted-foreground">
                                        {order.planned_date}
                                    </td>
                                    <td className="p-4 text-right">
                                        <TableActions
                                            actions={{
                                                view: true,
                                                edit: false,
                                                delete: false,
                                            }}
                                            onView={() =>
                                                router.get(
                                                    productionOrderShow({
                                                        production_order: order.id,
                                                    }).url,
                                                )
                                            }
                                        />
                                    </td>
                                </tr>
                            ))}
                            {orders.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="p-8 text-center text-sm text-muted-foreground"
                                    >
                                        No hay órdenes de producción registradas.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="mt-4 flex justify-center">
                    <Pagination links={orders.links} />
                </div>
            </div>
        </>
    );
}
