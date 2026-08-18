import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Download,
    FileText,
    Pencil,
    ShoppingCart,
} from 'lucide-react';
import { useEffect, useState } from 'react';

import { FormattedNumber } from '@/components/formatted-number';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import {
    convertToOrder as quotationsConvertToOrder,
    edit as quotationsEdit,
    exportPdf as quotationsExportPdf,
    index as quotationsIndex,
    updateStatus as quotationsUpdateStatus,
} from '@/routes/quotations';
import { show as salesOrdersShow } from '@/routes/sales-orders';

type QuotationItem = {
    id: number;
    product: { id: number; code: string | null; name: string };
    product_variant: {
        id: number;
        name: string;
        presentation_label: string | null;
    };
    description: string | null;
    color: string | null;
    type: string | null;
    type_label: string | null;
    quantity: number;
    list_unit_price: number;
    price_adjustment_pct: number;
    unit_price: number;
    subtotal: number;
};

type QuotationDetail = {
    id: number;
    quotation_number: number | null;
    client: {
        business_name: string | null;
        nit: string | null;
        contact_name: string | null;
        phone: string | null;
        shipping_address: string | null;
    };
    technology: string | null;
    line: string | null;
    thickness_mils: string | null;
    application_method: string | null;
    quotation_date: string | null;
    validity_days: number | null;
    validity_days_label: string | null;
    payment_method: string | null;
    payment_method_label: string | null;
    delivery_time: string | null;
    area: string | null;
    notes: string | null;
    subtotal: number;
    iva_percentage: number;
    iva_amount: number;
    total: number;
    status: string;
    status_label: string;
    items: QuotationItem[];
    created_at: string;
    creator: { name: string; email: string } | null;
};

type StatusOption = {
    value: string;
    label: string;
};

type Props = {
    quotation: QuotationDetail;
    can: {
        update: boolean;
        exportPdf: boolean;
        updateStatus: boolean;
        convertToOrder: boolean;
        viewSalesOrder: boolean;
    };
    salesOrderId: number | null;
    statusOptions: StatusOption[];
};

const statusColors: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-800',
    sent: 'bg-blue-100 text-blue-800',
    accepted: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
};

export default function QuotationsShow({
    quotation,
    can,
    salesOrderId,
    statusOptions,
}: Props) {
    const { data, setData, patch, processing } = useForm({
        status: quotation.status,
    });

    const [convertDialogOpen, setConvertDialogOpen] = useState(false);

    const convertForm = useForm({
        priority: 'medium',
        required_date: (() => {
            const d = new Date();
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        })(),
        notes: quotation.notes ?? '',
        shipping_address: quotation.client.shipping_address ?? '',
    });

    useEffect(() => {
        if (convertDialogOpen) {
            convertForm.reset();
            convertForm.clearErrors();
        }
    }, [convertDialogOpen]);

    const handleStatusUpdate = () => {
        patch(quotationsUpdateStatus(quotation.id).url, {
            preserveScroll: true,
        });
    };

    const handleConvertToOrder = () => {
        convertForm.post(quotationsConvertToOrder(quotation.id).url, {
            preserveScroll: true,
            onSuccess: () => setConvertDialogOpen(false),
        });
    };

    return (
        <>
            <Head
                title={
                    quotation.quotation_number
                        ? `Cotización No.${quotation.quotation_number}`
                        : 'Cotización'
                }
            />

            <div className="mx-auto max-w-6xl space-y-6 p-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={quotationsIndex().url}>
                                <ArrowLeft className="mr-1 h-4 w-4" />
                                Volver
                            </Link>
                        </Button>
                        <div>
                            <h1 className="flex items-center gap-2 text-2xl font-semibold">
                                <FileText className="h-6 w-6" />
                                {quotation.quotation_number
                                    ? `Cotización No.${quotation.quotation_number}`
                                    : 'Cotización'}
                            </h1>
                            <span
                                className={`mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[quotation.status] ?? ''}`}
                            >
                                {quotation.status_label}
                            </span>
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {salesOrderId && can.viewSalesOrder && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={salesOrdersShow(salesOrderId).url}
                                >
                                    <ShoppingCart className="mr-2 h-4 w-4" />
                                    Pedido #{salesOrderId}
                                </Link>
                            </Button>
                        )}
                        {can.exportPdf && (
                            <Button variant="outline" asChild>
                                <a
                                    href={quotationsExportPdf(quotation.id).url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Download className="mr-2 h-4 w-4" />
                                    Descargar PDF
                                </a>
                            </Button>
                        )}
                        {can.update && (
                            <Button asChild>
                                <Link href={quotationsEdit(quotation.id).url}>
                                    <Pencil className="mr-2 h-4 w-4" />
                                    Editar
                                </Link>
                            </Button>
                        )}
                        {can.convertToOrder && (
                            <Dialog
                                open={convertDialogOpen}
                                onOpenChange={(open) => {
                                    if (!convertForm.processing) {
                                        setConvertDialogOpen(open);
                                    }
                                }}
                            >
                                <DialogTrigger asChild>
                                    <Button>
                                        <ShoppingCart className="mr-2 h-4 w-4" />
                                        Convertir en pedido
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>
                                            Convertir cotización en pedido
                                        </DialogTitle>
                                        <DialogDescription>
                                            Se creará un pedido con los
                                            productos de esta cotización.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div className="space-y-4 py-2">
                                        {convertForm.errors.status && (
                                            <p className="text-sm text-destructive">
                                                {convertForm.errors.status}
                                            </p>
                                        )}
                                        <div className="space-y-2">
                                            <Label htmlFor="priority">
                                                Prioridad
                                            </Label>
                                            <Select
                                                value={convertForm.data.priority}
                                                onValueChange={(value) =>
                                                    convertForm.setData(
                                                        'priority',
                                                        value,
                                                    )
                                                }
                                            >
                                                <SelectTrigger id="priority">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="low">
                                                        Baja
                                                    </SelectItem>
                                                    <SelectItem value="medium">
                                                        Media
                                                    </SelectItem>
                                                    <SelectItem value="high">
                                                        Alta
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                            {convertForm.errors.priority && (
                                                <p className="text-sm text-destructive">
                                                    {convertForm.errors.priority}
                                                </p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="required_date">
                                                Fecha requerida
                                            </Label>
                                            <Input
                                                id="required_date"
                                                type="date"
                                                value={
                                                    convertForm.data.required_date
                                                }
                                                onChange={(e) =>
                                                    convertForm.setData(
                                                        'required_date',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            {convertForm.errors.required_date && (
                                                <p className="text-sm text-destructive">
                                                    {
                                                        convertForm.errors
                                                            .required_date
                                                    }
                                                </p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="shipping_address">
                                                Dirección de envío
                                            </Label>
                                            <Textarea
                                                id="shipping_address"
                                                rows={2}
                                                value={
                                                    convertForm.data
                                                        .shipping_address
                                                }
                                                onChange={(e) =>
                                                    convertForm.setData(
                                                        'shipping_address',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            {convertForm.errors
                                                .shipping_address && (
                                                <p className="text-sm text-destructive">
                                                    {
                                                        convertForm.errors
                                                            .shipping_address
                                                    }
                                                </p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="notes">Notas</Label>
                                            <Textarea
                                                id="notes"
                                                rows={3}
                                                value={convertForm.data.notes}
                                                onChange={(e) =>
                                                    convertForm.setData(
                                                        'notes',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            {convertForm.errors.notes && (
                                                <p className="text-sm text-destructive">
                                                    {convertForm.errors.notes}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                setConvertDialogOpen(false)
                                            }
                                            disabled={convertForm.processing}
                                        >
                                            Cancelar
                                        </Button>
                                        <Button
                                            onClick={handleConvertToOrder}
                                            disabled={convertForm.processing}
                                        >
                                            Confirmar conversión
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        )}
                    </div>
                </div>

                {can.updateStatus && (
                    <div className="flex flex-wrap items-end gap-3 rounded-lg border border-border bg-card p-4">
                        <div className="space-y-1">
                            <label className="text-sm font-medium">
                                Cambiar estado
                            </label>
                            <select
                                value={data.status}
                                onChange={(e) =>
                                    setData('status', e.target.value)
                                }
                                className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                {statusOptions.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <AlertDialog>
                            <AlertDialogTrigger asChild>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    disabled={
                                        processing ||
                                        data.status === quotation.status
                                    }
                                >
                                    Actualizar estado
                                </Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                                <AlertDialogHeader>
                                    <AlertDialogTitle>
                                        ¿Cambiar estado de la cotización?
                                    </AlertDialogTitle>
                                    <AlertDialogDescription>
                                        El estado pasará de{' '}
                                        <span className="font-semibold">
                                            {quotation.status_label}
                                        </span>{' '}
                                        a{' '}
                                        <span className="font-semibold">
                                            {
                                                statusOptions.find(
                                                    (o) =>
                                                        o.value === data.status,
                                                )?.label
                                            }
                                        </span>
                                        .
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel>
                                        Cancelar
                                    </AlertDialogCancel>
                                    <AlertDialogAction
                                        onClick={handleStatusUpdate}
                                    >
                                        Confirmar cambio
                                    </AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="rounded-lg border border-border bg-card p-6">
                        <h2 className="mb-4 text-lg font-semibold">Cliente</h2>
                        <dl className="space-y-2 text-sm">
                            <div className="flex justify-between gap-4">
                                <dt className="text-muted-foreground">
                                    Razón social
                                </dt>
                                <dd>{quotation.client.business_name}</dd>
                            </div>
                            <div className="flex justify-between gap-4">
                                <dt className="text-muted-foreground">NIT</dt>
                                <dd>{quotation.client.nit ?? '—'}</dd>
                            </div>
                            <div className="flex justify-between gap-4">
                                <dt className="text-muted-foreground">
                                    Contacto
                                </dt>
                                <dd>{quotation.client.contact_name ?? '—'}</dd>
                            </div>
                            <div className="flex justify-between gap-4">
                                <dt className="text-muted-foreground">
                                    Teléfono
                                </dt>
                                <dd>{quotation.client.phone ?? '—'}</dd>
                            </div>
                        </dl>
                    </div>

                    <div className="rounded-lg border border-border bg-card p-6">
                        <h2 className="mb-4 text-lg font-semibold">
                            Condiciones
                        </h2>
                        <dl className="space-y-2 text-sm">
                            <div className="flex justify-between gap-4">
                                <dt className="text-muted-foreground">
                                    Tecnología
                                </dt>
                                <dd>{quotation.technology ?? 'N.D.'}</dd>
                            </div>
                            <div className="flex justify-between gap-4">
                                <dt className="text-muted-foreground">Línea</dt>
                                <dd>{quotation.line ?? 'N.D.'}</dd>
                            </div>
                            <div className="flex justify-between gap-4">
                                <dt className="text-muted-foreground">
                                    Forma de pago
                                </dt>
                                <dd>
                                    {quotation.payment_method_label ??
                                        quotation.payment_method ??
                                        'N.D.'}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-4">
                                <dt className="text-muted-foreground">
                                    Entrega
                                </dt>
                                <dd>{quotation.delivery_time ?? 'N.D.'}</dd>
                            </div>
                            <div className="flex justify-between gap-4">
                                <dt className="text-muted-foreground">
                                    Validez
                                </dt>
                                <dd>
                                    {quotation.validity_days_label ??
                                        (quotation.validity_days
                                            ? `${quotation.validity_days} días`
                                            : 'N.D.')}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <div className="overflow-hidden rounded-lg border border-border bg-card">
                    <table className="w-full text-sm">
                        <thead className="border-b border-border bg-muted/30">
                            <tr>
                                <th className="px-4 py-3 text-left">
                                    Producto
                                </th>
                                <th className="px-4 py-3 text-left">Tipo</th>
                                <th className="px-4 py-3 text-left">
                                    Descripción
                                </th>
                                <th className="px-4 py-3 text-right">Cant.</th>
                                <th className="px-4 py-3 text-right">
                                    Precio lista
                                </th>
                                <th className="px-4 py-3 text-right">
                                    Ajuste %
                                </th>
                                <th className="px-4 py-3 text-right">
                                    Precio unit.
                                </th>
                                <th className="px-4 py-3 text-right">
                                    Subtotal
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {quotation.items.map((item) => (
                                <tr
                                    key={item.id}
                                    className="border-b border-border last:border-0"
                                >
                                    <td className="px-4 py-3">
                                        <div className="font-medium">
                                            {item.product.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {item.product_variant.name}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        {item.type_label ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {item.description ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {item.quantity}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <FormattedNumber
                                            value={item.list_unit_price}
                                            currency
                                            maxDecimals={0}
                                        />
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <FormattedNumber
                                            value={item.price_adjustment_pct}
                                            percent
                                            maxDecimals={2}
                                        />
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <FormattedNumber
                                            value={item.unit_price}
                                            currency
                                            maxDecimals={0}
                                        />
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <FormattedNumber
                                            value={item.subtotal}
                                            currency
                                            maxDecimals={0}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="ml-auto max-w-sm rounded-lg border border-border bg-card p-6">
                    <div className="space-y-2 text-sm">
                        <div className="flex justify-between">
                            <span>Subtotal</span>
                            <FormattedNumber
                                value={quotation.subtotal}
                                currency
                                maxDecimals={0}
                            />
                        </div>
                        <div className="flex justify-between">
                            <span>IVA {quotation.iva_percentage}%</span>
                            <FormattedNumber
                                value={quotation.iva_amount}
                                currency
                                maxDecimals={0}
                            />
                        </div>
                        <div className="flex justify-between border-t border-border pt-2 text-base font-semibold">
                            <span>Total</span>
                            <FormattedNumber
                                value={quotation.total}
                                currency
                                maxDecimals={0}
                            />
                        </div>
                    </div>
                </div>

                {quotation.notes && (
                    <div className="rounded-lg border border-border bg-card p-6">
                        <h2 className="mb-2 text-lg font-semibold">Notas</h2>
                        <p className="text-sm text-muted-foreground">
                            {quotation.notes}
                        </p>
                    </div>
                )}

                <div className="text-sm text-muted-foreground">
                    Creada el {quotation.created_at}
                    {quotation.creator ? ` por ${quotation.creator.name}` : ''}
                </div>
            </div>
        </>
    );
}
