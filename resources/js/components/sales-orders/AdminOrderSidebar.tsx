import { useForm } from '@inertiajs/react';
import { RefreshCw, Save } from 'lucide-react';

import { FormattedDate } from '@/components/formatted-date';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    update as salesOrderUpdate,
    updateStatus as salesOrderUpdateStatus,
} from '@/routes/sales-orders';

type StatusTransition = {
    value: string;
    label: string;
};

type SalesOrderMeta = {
    id: number;
    status: string;
    status_label: string;
    priority: string;
    priority_label: string;
    estimated_delivery_date: string | null;
    notes: string | null;
    shipping_address: string | null;
    required_date: string | null;
    created_at: string;
    creator: { name: string } | null;
    client: { contact_name: string | null; phone: string | null };
};

type Props = {
    order: SalesOrderMeta;
    statusTransitions: StatusTransition[];
    can: {
        /** Editar datos del pedido (solo mientras está pendiente). */
        edit: boolean;
        /** Cambiar el estado del pedido. */
        updateStatus: boolean;
    };
};

export default function AdminOrderSidebar({
    order,
    statusTransitions,
    can,
}: Props) {
    const statusForm = useForm({
        status: order.status,
    });

    const dataForm = useForm({
        priority: order.priority,
        estimated_delivery_date: order.estimated_delivery_date ?? '',
        notes: order.notes ?? '',
        client_contact_name: order.client.contact_name ?? '',
        client_phone: order.client.phone ?? '',
    });

    const handleStatusUpdate = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        statusForm.patch(salesOrderUpdateStatus(order.id).url);
    };

    const handleDataUpdate = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        dataForm.patch(salesOrderUpdate(order.id).url);
    };

    return (
        <div className="space-y-6">
            {can.updateStatus && statusTransitions.length > 0 && (
                <form
                    onSubmit={handleStatusUpdate}
                    className="space-y-4 rounded-lg border border-border bg-card p-6"
                >
                    <h2 className="text-lg font-semibold">Estado</h2>

                    <div className="space-y-2">
                        <Label htmlFor="status">Estado del pedido</Label>
                        <Select
                            value={statusForm.data.status}
                            onValueChange={(value) =>
                                statusForm.setData('status', value)
                            }
                        >
                            <SelectTrigger id="status" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={order.status}>
                                    {order.status_label}
                                </SelectItem>
                                {statusTransitions.map((t) => (
                                    <SelectItem key={t.value} value={t.value}>
                                        {t.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {statusForm.errors.status && (
                            <p className="text-sm text-destructive">
                                {statusForm.errors.status}
                            </p>
                        )}
                    </div>

                    <Button
                        type="submit"
                        className="w-full"
                        disabled={
                            statusForm.processing ||
                            statusForm.data.status === order.status
                        }
                    >
                        <RefreshCw className="mr-2 h-4 w-4" />
                        Actualizar estado
                    </Button>
                </form>
            )}

            {can.edit && (
                <form
                    onSubmit={handleDataUpdate}
                    className="space-y-4 rounded-lg border border-border bg-card p-6"
                >
                    <h2 className="text-lg font-semibold">Datos del pedido</h2>

                    <div className="space-y-2">
                        <Label htmlFor="priority">Prioridad</Label>
                        <Select
                            value={dataForm.data.priority}
                            onValueChange={(value) =>
                                dataForm.setData('priority', value)
                            }
                        >
                            <SelectTrigger id="priority" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="low">Baja</SelectItem>
                                <SelectItem value="medium">Media</SelectItem>
                                <SelectItem value="high">Alta</SelectItem>
                            </SelectContent>
                        </Select>
                        {dataForm.errors.priority && (
                            <p className="text-sm text-destructive">
                                {dataForm.errors.priority}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="estimated_delivery_date">
                            Fecha estimada de entrega
                        </Label>
                        <Input
                            id="estimated_delivery_date"
                            type="date"
                            value={dataForm.data.estimated_delivery_date}
                            onChange={(e) =>
                                dataForm.setData(
                                    'estimated_delivery_date',
                                    e.target.value,
                                )
                            }
                        />
                        {dataForm.errors.estimated_delivery_date && (
                            <p className="text-sm text-destructive">
                                {dataForm.errors.estimated_delivery_date}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="client_contact_name">Contacto</Label>
                        <Input
                            id="client_contact_name"
                            value={dataForm.data.client_contact_name}
                            onChange={(e) =>
                                dataForm.setData(
                                    'client_contact_name',
                                    e.target.value,
                                )
                            }
                        />
                        {dataForm.errors.client_contact_name && (
                            <p className="text-sm text-destructive">
                                {dataForm.errors.client_contact_name}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="client_phone">Teléfono</Label>
                        <Input
                            id="client_phone"
                            value={dataForm.data.client_phone}
                            onChange={(e) =>
                                dataForm.setData('client_phone', e.target.value)
                            }
                        />
                        {dataForm.errors.client_phone && (
                            <p className="text-sm text-destructive">
                                {dataForm.errors.client_phone}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="notes">Notas</Label>
                        <textarea
                            id="notes"
                            rows={3}
                            value={dataForm.data.notes}
                            onChange={(e) =>
                                dataForm.setData('notes', e.target.value)
                            }
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background"
                        />
                        {dataForm.errors.notes && (
                            <p className="text-sm text-destructive">
                                {dataForm.errors.notes}
                            </p>
                        )}
                    </div>

                    <Button
                        type="submit"
                        className="w-full"
                        disabled={dataForm.processing}
                    >
                        <Save className="mr-2 h-4 w-4" />
                        Guardar cambios
                    </Button>
                </form>
            )}

            <div className="space-y-2 rounded-lg border border-border bg-card p-6 text-sm">
                <h2 className="text-lg font-semibold">Metadatos</h2>
                <p>
                    <span className="text-muted-foreground">Creado por:</span>{' '}
                    {order.creator?.name ?? '-'}
                </p>
                <p>
                    <span className="text-muted-foreground">
                        Fecha creación:
                    </span>{' '}
                    <FormattedDate value={order.created_at} format="datetime" />
                </p>
                <p>
                    <span className="text-muted-foreground">Fecha req.:</span>{' '}
                    <FormattedDate
                        value={order.required_date}
                        format="short"
                        emptyValue="-"
                    />
                </p>
                {order.shipping_address && (
                    <p>
                        <span className="text-muted-foreground">
                            Dirección:
                        </span>{' '}
                        <span className="whitespace-pre-wrap">
                            {order.shipping_address}
                        </span>
                    </p>
                )}
            </div>
        </div>
    );
}
