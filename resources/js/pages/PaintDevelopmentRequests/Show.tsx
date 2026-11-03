import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, Clock, FileText, XCircle } from 'lucide-react';
import { useState } from 'react';

import { DetailItem, DetailList } from '@/components/detail-list';
import { FormattedDate } from '@/components/formatted-date';
import { FormattedNumber } from '@/components/formatted-number';
import { PageHeader } from '@/components/page-header';
import { payloadFieldApplies } from '@/components/paint-development-requests/conditional-fields';
import { isNumericPayloadValue } from '@/components/paint-development-requests/numeric-payload';
import { StatusBadge } from '@/components/status-badge';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    edit as requestsEdit,
    index as requestsIndex,
    show as requestsShow,
    submit as requestsSubmit,
    updateStatus as requestsUpdateStatus,
    exportPdf as requestsExportPdf,
} from '@/routes/paint-development-requests';

type Payload = Record<string, unknown>;

type Props = {
    request: {
        id: number;
        request_number: number;
        client_name: string | null;
        project_name: string;
        responsible: string;
        city: string;
        sample_due_date: string | null;
        current_product: string | null;
        context_payload: Payload;
        performance_payload: Payload;
        application_payload: Payload;
        specifications_payload: Payload;
        schema_version: number;
        status: string;
        status_label: string;
        review_notes: string | null;
        reviewed_at: string | null;
        reviewer: { name: string } | null;
        created_at: string;
        creator: { name: string; email: string } | null;
    };
    can: {
        update: boolean;
        exportPdf: boolean;
        updateStatus: boolean;
        submit: boolean;
    };
    nextStatusOptions: { id: string; label: string }[];
};

function formatArrayValue(value: unknown): string {
    if (Array.isArray(value)) {
        return value.join(', ');
    }

    if (value === null || value === undefined) {
        return '';
    }

    return String(value);
}

// Una sección de respuestas, a todo el ancho y en dos columnas.
function PayloadSection({
    title,
    payloadKey,
    payload,
}: {
    title: string;
    payloadKey:
        | 'context_payload'
        | 'performance_payload'
        | 'application_payload'
        | 'specifications_payload';
    payload: Payload;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent>
                <DetailList columns={2}>
                    {Object.entries(payload)
                        .filter(([key]) => payloadFieldApplies(key, payload))
                        .map(([key, value]) => (
                            <DetailItem
                                key={key}
                                label={key
                                    .replace(/_/g, ' ')
                                    .replace(/^\w/, (c) => c.toUpperCase())}
                                value={
                                    isNumericPayloadValue(
                                        payloadKey,
                                        key,
                                        value,
                                    ) ? (
                                        <FormattedNumber
                                            value={value}
                                            kind="quantity"
                                        />
                                    ) : (
                                        formatArrayValue(value)
                                    )
                                }
                                valueClassName="whitespace-pre-wrap"
                            />
                        ))}
                </DetailList>
            </CardContent>
        </Card>
    );
}

export default function PaintDevelopmentRequestShow({
    request,
    can,
    nextStatusOptions,
}: Props) {
    const [statusDialogOpen, setStatusDialogOpen] = useState(false);
    const [selectedStatus, setSelectedStatus] = useState('');
    const [reviewNotes, setReviewNotes] = useState('');
    const [submitDialogOpen, setSubmitDialogOpen] = useState(false);
    // El servidor puede rechazar el envío (por ejemplo, la fecha de muestra ya pasó: D-PDR2).
    const [submitError, setSubmitError] = useState<string | null>(null);

    const handleStatusSubmit = () => {
        router.patch(
            requestsUpdateStatus(request.id).url,
            { status: selectedStatus, review_notes: reviewNotes },
            {
                onSuccess: () => setStatusDialogOpen(false),
            },
        );
    };

    const statusIcon =
        request.status === 'approved' ? (
            <CheckCircle2 className="h-5 w-5 text-success-text" />
        ) : request.status === 'rejected' ? (
            <XCircle className="h-5 w-5 text-destructive-text" />
        ) : (
            <Clock className="h-5 w-5 text-info-text" />
        );

    return (
        <>
            <Head title={`Solicitud ${request.request_number}`} />

            <div className="space-y-6 p-6">
                <PageHeader
                    title={`Solicitud ${request.request_number}`}
                    badge={
                        <div className="flex items-center gap-2">
                            {statusIcon}
                            <StatusBadge
                                enum="PaintDevelopmentRequestStatus"
                                value={request.status}
                            />
                        </div>
                    }
                    actions={
                        <>
                            {can.submit && (
                                <Button
                                    variant="default"
                                    onClick={() => setSubmitDialogOpen(true)}
                                >
                                    Enviar
                                </Button>
                            )}
                            {can.update && (
                                <Button variant="outline" asChild>
                                    <Link href={requestsEdit(request.id).url}>
                                        Editar
                                    </Link>
                                </Button>
                            )}
                            {can.exportPdf && (
                                <Button variant="outline" asChild>
                                    <a
                                        href={requestsExportPdf(request.id).url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <FileText className="size-4" />
                                        Ver PDF
                                    </a>
                                </Button>
                            )}
                            {can.updateStatus &&
                                nextStatusOptions.length > 0 && (
                                    <Button
                                        variant="secondary"
                                        onClick={() => {
                                            setSelectedStatus('');
                                            setReviewNotes(
                                                request.review_notes ?? '',
                                            );
                                            setStatusDialogOpen(true);
                                        }}
                                    >
                                        Cambiar estado
                                    </Button>
                                )}
                        </>
                    }
                />

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Identificación</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <DetailList columns={1}>
                                <DetailItem
                                    label="Proyecto"
                                    value={request.project_name}
                                />
                                <DetailItem
                                    label="Cliente"
                                    value={request.client_name}
                                />
                                <DetailItem
                                    label="Responsable"
                                    value={request.responsible}
                                />
                                <DetailItem
                                    label="Ciudad"
                                    value={request.city}
                                />
                                <DetailItem
                                    label="Fecha muestra"
                                    value={
                                        <FormattedDate
                                            value={request.sample_due_date}
                                            format="short"
                                        />
                                    }
                                />
                                <DetailItem
                                    label="Producto actual"
                                    value={request.current_product}
                                />
                            </DetailList>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Auditoría</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <DetailList columns={1}>
                                <DetailItem
                                    label="Creado por"
                                    value={request.creator?.name}
                                />
                                <DetailItem
                                    label="Fecha creación"
                                    value={
                                        <FormattedDate
                                            value={request.created_at}
                                            format="datetime"
                                        />
                                    }
                                />
                                {request.reviewer && (
                                    <>
                                        <DetailItem
                                            label="Revisado por"
                                            value={request.reviewer.name}
                                        />
                                        <DetailItem
                                            label="Fecha revisión"
                                            value={
                                                <FormattedDate
                                                    value={request.reviewed_at}
                                                    format="datetime"
                                                />
                                            }
                                        />
                                    </>
                                )}
                                {request.review_notes && (
                                    <DetailItem
                                        label="Notas de revisión"
                                        value={request.review_notes}
                                        valueClassName="whitespace-pre-wrap"
                                    />
                                )}
                            </DetailList>
                        </CardContent>
                    </Card>
                </div>

                <PayloadSection
                    title="1. Contexto — Proyecto, sustrato y exposición"
                    payloadKey="context_payload"
                    payload={request.context_payload}
                />

                <PayloadSection
                    title="2. Desempeño — Función, resistencia y acabado"
                    payloadKey="performance_payload"
                    payload={request.performance_payload}
                />

                <PayloadSection
                    title="3. Aplicación — Método, espesores y tecnología"
                    payloadKey="application_payload"
                    payload={request.application_payload}
                />

                <PayloadSection
                    title="4. Especificaciones — Control, suministro y aprobación"
                    payloadKey="specifications_payload"
                    payload={request.specifications_payload}
                />
            </div>

            <Dialog open={statusDialogOpen} onOpenChange={setStatusDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Cambiar estado</DialogTitle>
                        <DialogDescription>
                            Selecciona el nuevo estado para esta solicitud.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-3 py-2">
                        <select
                            value={selectedStatus}
                            onChange={(e) => setSelectedStatus(e.target.value)}
                            className="h-10 w-full rounded-md border border-input bg-background px-3 text-base md:text-sm"
                        >
                            <option value="">Selecciona un estado</option>
                            {nextStatusOptions.map((opt) => (
                                <option key={opt.id} value={opt.id}>
                                    {opt.label}
                                </option>
                            ))}
                        </select>
                        <div className="space-y-1">
                            <Label htmlFor="review_notes">
                                Notas de revisión
                            </Label>
                            <Textarea
                                id="review_notes"
                                value={reviewNotes}
                                onChange={(e) => setReviewNotes(e.target.value)}
                                placeholder="Observaciones sobre la decisión..."
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setStatusDialogOpen(false)}
                        >
                            Cancelar
                        </Button>
                        <Button
                            onClick={handleStatusSubmit}
                            disabled={!selectedStatus}
                        >
                            Guardar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Confirm submit dialog */}
            <Dialog
                open={submitDialogOpen}
                onOpenChange={(open) => {
                    setSubmitDialogOpen(open);
                    setSubmitError(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Enviar solicitud</DialogTitle>
                        <DialogDescription>
                            ¿Enviar esta solicitud para revisión? No podrás
                            editarla después.
                        </DialogDescription>
                    </DialogHeader>
                    {submitError && (
                        <p className="text-sm text-destructive">
                            {submitError}
                        </p>
                    )}
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setSubmitDialogOpen(false)}
                        >
                            Cancelar
                        </Button>
                        <Button
                            onClick={() => {
                                router.patch(
                                    requestsSubmit(request.id).url,
                                    {},
                                    {
                                        onSuccess: () =>
                                            setSubmitDialogOpen(false),
                                        onError: (errors) =>
                                            setSubmitError(
                                                Object.values(errors)[0] ??
                                                    null,
                                            ),
                                    },
                                );
                            }}
                        >
                            Enviar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

// Las migas son la única forma de volver al listado (DESIGN.md §10).
PaintDevelopmentRequestShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Desarrollo de pinturas', href: requestsIndex() },
        {
            title: `Solicitud ${props.request.request_number}`,
            href: requestsShow(props.request.id),
        },
    ],
});
