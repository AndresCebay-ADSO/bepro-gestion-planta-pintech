import { useForm } from '@inertiajs/react';
import { RefreshCw, Save } from 'lucide-react';

import { EmptyCell } from '@/components/empty-cell';
import { FormField } from '@/components/form-field';
import { FormattedDate } from '@/components/formatted-date';
import { PlantDateInput } from '@/components/plant-date-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
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
    /** Día de planta en que se creó el pedido: la fecha estimada de entrega no puede ser anterior (D-PV3). */
    created_on: string | null;
    creator: { name: string } | null;
    client: { contact_name: string | null; phone: string | null };
};

type Props = {
    order: SalesOrderMeta;
    statusTransitions: StatusTransition[];
    can: {
        /** Editar datos del pedido (solo mientras está pendiente). */
        update: boolean;
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
                    noValidate
                    onSubmit={handleStatusUpdate}
                    className="space-y-4 rounded-lg border border-border bg-card p-6"
                >
                    <h2 className="text-lg font-semibold">Estado</h2>

                    <FormField
                        id="status"
                        label="Estado del pedido"
                        error={statusForm.errors.status}
                    >
                        {(control) => (
                            <Select
                                value={statusForm.data.status}
                                onValueChange={(value) =>
                                    statusForm.setData('status', value)
                                }
                            >
                                <SelectTrigger {...control}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={order.status}>
                                        {order.status_label}
                                    </SelectItem>
                                    {statusTransitions.map((t) => (
                                        <SelectItem
                                            key={t.value}
                                            value={t.value}
                                        >
                                            {t.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </FormField>

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

            {can.update && (
                <form
                    noValidate
                    onSubmit={handleDataUpdate}
                    className="space-y-4 rounded-lg border border-border bg-card p-6"
                >
                    <h2 className="text-lg font-semibold">Datos del pedido</h2>

                    <FormField
                        id="priority"
                        label="Prioridad"
                        error={dataForm.errors.priority}
                    >
                        {(control) => (
                            <Select
                                value={dataForm.data.priority}
                                onValueChange={(value) =>
                                    dataForm.setData('priority', value)
                                }
                            >
                                <SelectTrigger {...control}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="low">Baja</SelectItem>
                                    <SelectItem value="medium">
                                        Media
                                    </SelectItem>
                                    <SelectItem value="high">Alta</SelectItem>
                                </SelectContent>
                            </Select>
                        )}
                    </FormField>

                    <FormField
                        id="estimated_delivery_date"
                        label="Fecha estimada de entrega"
                        error={dataForm.errors.estimated_delivery_date}
                    >
                        <PlantDateInput
                            clearable
                            min={order.created_on}
                            saved={order.estimated_delivery_date}
                            value={dataForm.data.estimated_delivery_date}
                            onChange={(value) =>
                                dataForm.setData(
                                    'estimated_delivery_date',
                                    value,
                                )
                            }
                        />
                    </FormField>

                    <FormField
                        id="client_contact_name"
                        label="Contacto"
                        error={dataForm.errors.client_contact_name}
                    >
                        <Input
                            value={dataForm.data.client_contact_name}
                            onChange={(e) =>
                                dataForm.setData(
                                    'client_contact_name',
                                    e.target.value,
                                )
                            }
                        />
                    </FormField>

                    <FormField
                        id="client_phone"
                        label="Teléfono"
                        error={dataForm.errors.client_phone}
                    >
                        <Input
                            value={dataForm.data.client_phone}
                            onChange={(e) =>
                                dataForm.setData('client_phone', e.target.value)
                            }
                        />
                    </FormField>

                    <FormField
                        id="notes"
                        label="Notas"
                        error={dataForm.errors.notes}
                    >
                        <Textarea
                            rows={3}
                            value={dataForm.data.notes}
                            onChange={(e) =>
                                dataForm.setData('notes', e.target.value)
                            }
                        />
                    </FormField>

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
                    {order.creator?.name ?? <EmptyCell />}
                </p>
                <p>
                    <span className="text-muted-foreground">
                        Fecha creación:
                    </span>{' '}
                    <FormattedDate value={order.created_at} format="datetime" />
                </p>
                <p>
                    <span className="text-muted-foreground">Fecha req.:</span>{' '}
                    <FormattedDate value={order.required_date} format="short" />
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
