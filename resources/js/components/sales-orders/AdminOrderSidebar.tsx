import { useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';

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
import { update as salesOrderUpdate } from '@/routes/sales-orders';

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
};

export default function AdminOrderSidebar({ order, statusTransitions }: Props) {
    const { data, setData, patch, processing, errors } = useForm({
        status: order.status,
        priority: order.priority,
        estimated_delivery_date: order.estimated_delivery_date ?? '',
        notes: order.notes ?? '',
        client_contact_name: order.client.contact_name ?? '',
        client_phone: order.client.phone ?? '',
    });

    const handleUpdate = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        patch(salesOrderUpdate(order.id).url);
    };

    return (
        <div className="space-y-6">
            <form
                onSubmit={handleUpdate}
                className="space-y-4 rounded-lg border border-border bg-card p-6"
            >
                <h2 className="text-lg font-semibold">Acciones</h2>

                {statusTransitions.length > 0 && (
                    <div className="space-y-2">
                        <Label htmlFor="status">Estado</Label>
                        <Select
                            value={data.status}
                            onValueChange={(value) => setData('status', value)}
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
                        {errors.status && (
                            <p className="text-sm text-destructive">
                                {errors.status}
                            </p>
                        )}
                    </div>
                )}

                <div className="space-y-2">
                    <Label htmlFor="priority">Prioridad</Label>
                    <Select
                        value={data.priority}
                        onValueChange={(value) => setData('priority', value)}
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
                    {errors.priority && (
                        <p className="text-sm text-destructive">
                            {errors.priority}
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
                        value={data.estimated_delivery_date}
                        onChange={(e) =>
                            setData('estimated_delivery_date', e.target.value)
                        }
                    />
                    {errors.estimated_delivery_date && (
                        <p className="text-sm text-destructive">
                            {errors.estimated_delivery_date}
                        </p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="client_contact_name">Contacto</Label>
                    <Input
                        id="client_contact_name"
                        value={data.client_contact_name}
                        onChange={(e) =>
                            setData('client_contact_name', e.target.value)
                        }
                    />
                    {errors.client_contact_name && (
                        <p className="text-sm text-destructive">
                            {errors.client_contact_name}
                        </p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="client_phone">Teléfono</Label>
                    <Input
                        id="client_phone"
                        value={data.client_phone}
                        onChange={(e) =>
                            setData('client_phone', e.target.value)
                        }
                    />
                    {errors.client_phone && (
                        <p className="text-sm text-destructive">
                            {errors.client_phone}
                        </p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="notes">Notas</Label>
                    <textarea
                        id="notes"
                        rows={3}
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background"
                    />
                    {errors.notes && (
                        <p className="text-sm text-destructive">
                            {errors.notes}
                        </p>
                    )}
                </div>

                <Button type="submit" className="w-full" disabled={processing}>
                    <Save className="mr-2 h-4 w-4" />
                    Guardar Cambios
                </Button>
            </form>

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
                    {order.created_at}
                </p>
                <p>
                    <span className="text-muted-foreground">Fecha req.:</span>{' '}
                    {order.required_date ?? '-'}
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
