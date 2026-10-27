import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    Clock,
    FileDown,
    Pencil,
    Send,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';

import { FormattedDate } from '@/components/formatted-date';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';

import { Button } from '@/components/ui/button';
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

function SectionCard({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <div className="rounded-lg border border-border bg-card p-5 shadow-sm">
            <h2 className="mb-4 text-lg font-semibold text-foreground">
                {title}
            </h2>
            {children}
        </div>
    );
}

function FieldRow({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="grid grid-cols-1 gap-1 py-2 sm:grid-cols-[200px_1fr]">
            <span className="text-sm text-muted-foreground">{label}</span>
            <span className="text-sm font-medium text-foreground">
                {value ?? '—'}
            </span>
        </div>
    );
}

function formatArrayValue(value: unknown): string {
    if (Array.isArray(value)) {
        return value.join(', ');
    }

    if (value === null || value === undefined) {
        return '';
    }

    return String(value);
}

function PayloadSection({ payload }: { payload: Payload }) {
    return (
        <div className="divide-y divide-border">
            {Object.entries(payload).map(([key, value]) => (
                <FieldRow
                    key={key}
                    label={key
                        .replace(/_/g, ' ')
                        .replace(/^\w/, (c) => c.toUpperCase())}
                    value={formatArrayValue(value)}
                />
            ))}
        </div>
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

            <div className="space-y-4 p-6">
                <div className="flex items-start gap-3">
                    <Button
                        variant="outline"
                        size="icon"
                        asChild
                        className="mt-0.5 shrink-0"
                    >
                        <Link href={requestsIndex().url}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div className="min-w-0 flex-1">
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
                                            onClick={() =>
                                                setSubmitDialogOpen(true)
                                            }
                                        >
                                            <Send className="mr-2 h-4 w-4" />
                                            Enviar
                                        </Button>
                                    )}
                                    {can.update && (
                                        <Button variant="outline" asChild>
                                            <Link
                                                href={
                                                    requestsEdit(request.id).url
                                                }
                                            >
                                                <Pencil className="mr-2 h-4 w-4" />
                                                Editar
                                            </Link>
                                        </Button>
                                    )}
                                    {can.exportPdf && (
                                        <Button variant="outline" asChild>
                                            <a
                                                href={
                                                    requestsExportPdf(
                                                        request.id,
                                                    ).url
                                                }
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <FileDown className="mr-2 h-4 w-4" />
                                                PDF
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
                                                        request.review_notes ??
                                                            '',
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
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <SectionCard title="Identificación">
                        <FieldRow
                            label="Proyecto"
                            value={request.project_name}
                        />
                        <FieldRow
                            label="Cliente"
                            value={request.client_name ?? '—'}
                        />
                        <FieldRow
                            label="Responsable"
                            value={request.responsible}
                        />
                        <FieldRow label="Ciudad" value={request.city} />
                        <FieldRow
                            label="Fecha muestra"
                            value={
                                <FormattedDate
                                    value={request.sample_due_date}
                                    format="short"
                                    emptyValue="—"
                                />
                            }
                        />
                        <FieldRow
                            label="Producto actual"
                            value={request.current_product}
                        />
                    </SectionCard>

                    <SectionCard title="Auditoría">
                        <FieldRow
                            label="Creado por"
                            value={request.creator?.name ?? '—'}
                        />
                        <FieldRow
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
                                <FieldRow
                                    label="Revisado por"
                                    value={request.reviewer.name}
                                />
                                <FieldRow
                                    label="Fecha revisión"
                                    value={
                                        <FormattedDate
                                            value={request.reviewed_at}
                                            format="datetime"
                                            emptyValue="—"
                                        />
                                    }
                                />
                            </>
                        )}
                        {request.review_notes && (
                            <FieldRow
                                label="Notas de revisión"
                                value={request.review_notes}
                            />
                        )}
                    </SectionCard>
                </div>

                <SectionCard title="1. Contexto — Proyecto, sustrato y exposición">
                    <PayloadSection payload={request.context_payload} />
                </SectionCard>

                <SectionCard title="2. Desempeño — Función, resistencia y acabado">
                    <PayloadSection payload={request.performance_payload} />
                </SectionCard>

                <SectionCard title="3. Aplicación — Método, espesores y tecnología">
                    <PayloadSection payload={request.application_payload} />
                </SectionCard>

                <SectionCard title="4. Especificaciones — Control, suministro y aprobación">
                    <PayloadSection payload={request.specifications_payload} />
                </SectionCard>
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
                            className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
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
