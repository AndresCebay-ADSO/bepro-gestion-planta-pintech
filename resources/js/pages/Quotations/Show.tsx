import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Download, FileText, Pencil } from 'lucide-react';

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
    edit as quotationsEdit,
    exportPdf as quotationsExportPdf,
    index as quotationsIndex,
    updateStatus as quotationsUpdateStatus,
} from '@/routes/quotations';

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
    };
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
    statusOptions,
}: Props) {
    const { data, setData, patch, processing } = useForm({
        status: quotation.status,
    });

    const handleStatusUpdate = () => {
        patch(quotationsUpdateStatus(quotation.id).url, {
            preserveScroll: true,
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
