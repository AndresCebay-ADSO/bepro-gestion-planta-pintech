import { Head, Link, router } from '@inertiajs/react';
import {
    Copy,
    Download,
    ExternalLink,
    FileText,
    QrCode as QrCodeIcon,
} from 'lucide-react';
import { useState } from 'react';

import { ActiveBadge } from '@/components/active-badge';
import { DetailTable } from '@/components/detail-table';
import { EmptyCell } from '@/components/empty-cell';
import { FormattedDate } from '@/components/formatted-date';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { show as productionOrderShow } from '@/routes/production-orders';
import {
    index as qrCodesIndex,
    show as qrCodesShow,
    update as qrCodesUpdate,
} from '@/routes/qr-codes';

type QrDocumentItem = {
    id: number;
    file_name: string;
    document_type: string;
    version: number;
    is_current: boolean;
    created_at: string | null;
    uploaded_by: { id: number; name: string } | null;
    download_url: string;
};

type QrCodeDetail = {
    id: number;
    token: string;
    is_active: boolean;
    landing_url: string;
    image_url: string;
    created_at: string | null;
    created_by: { id: number; name: string } | null;
    product: {
        id: number;
        name: string;
        code: string;
        /** Nombre con el color de la orden (3.4). */
        display_name: string;
        description: string | null;
    } | null;
    production_order: {
        id: number;
        order_number: string;
        lot_number: number;
        completion_date: string | null;
        planned_date: string | null;
    } | null;
    documents: QrDocumentItem[];
};

type Props = {
    qrCode: QrCodeDetail;
    can: {
        update: boolean;
        viewProductionOrders: boolean;
    };
};

export default function QrCodesShow({ qrCode, can }: Props) {
    const [copied, setCopied] = useState(false);

    const handleCopyUrl = () => {
        navigator.clipboard.writeText(qrCode.landing_url).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    };

    const handleToggleActive = () => {
        router.patch(
            qrCodesUpdate(qrCode.id).url,
            { is_active: !qrCode.is_active },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={`QR — ${qrCode.token}`} />

            <div className="space-y-6 p-6">
                <PageHeader
                    title={`QR — ${qrCode.token}`}
                    badge={<ActiveBadge isActive={qrCode.is_active} />}
                    actions={
                        can.update && (
                            <Button
                                variant="outline"
                                onClick={handleToggleActive}
                            >
                                {qrCode.is_active ? 'Desactivar' : 'Activar'}
                            </Button>
                        )
                    }
                />

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* QR Image Card */}
                    <Card className="lg:col-span-1">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <QrCodeIcon className="h-5 w-5 text-primary" />
                                Código QR
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex justify-center rounded-lg border border-border bg-white p-4">
                                <img
                                    src={qrCode.image_url}
                                    alt={`Código QR ${qrCode.token}`}
                                    width={200}
                                    height={200}
                                    className="block"
                                />
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                asChild
                                className="w-full"
                            >
                                <a
                                    href={qrCode.image_url}
                                    download={`qr-${qrCode.token}.png`}
                                >
                                    <Download className="mr-1.5 h-4 w-4" />
                                    Descargar PNG
                                </a>
                            </Button>
                        </CardContent>
                    </Card>

                    {/* Metadata Card */}
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Información del lote</CardTitle>
                            <CardDescription>
                                Datos del producto y orden de producción
                                asociados.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {/* URL pública */}
                            <div className="rounded-md border border-border bg-background/80 px-3 py-2">
                                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    URL pública
                                </p>
                                <div className="flex items-center gap-2">
                                    <p
                                        className="flex-1 truncate font-mono text-xs text-foreground"
                                        title={qrCode.landing_url}
                                    >
                                        {qrCode.landing_url}
                                    </p>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={handleCopyUrl}
                                    >
                                        <Copy className="mr-1.5 h-3.5 w-3.5" />
                                        {copied ? 'Copiado' : 'Copiar'}
                                    </Button>
                                    <Button variant="ghost" size="sm" asChild>
                                        <a
                                            href={qrCode.landing_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <ExternalLink className="mr-1.5 h-3.5 w-3.5" />
                                            Abrir
                                        </a>
                                    </Button>
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        Producto
                                    </p>
                                    {qrCode.product ? (
                                        <div className="mt-1">
                                            <p className="font-medium text-foreground">
                                                {qrCode.product.display_name}
                                            </p>
                                            {qrCode.product.description && (
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {qrCode.product.description}
                                                </p>
                                            )}
                                        </div>
                                    ) : (
                                        <p>
                                            <EmptyCell />
                                        </p>
                                    )}
                                </div>
                                <div>
                                    <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        Orden de Producción
                                    </p>
                                    {qrCode.production_order ? (
                                        <div className="mt-1">
                                            {can.viewProductionOrders ? (
                                                <Link
                                                    href={
                                                        productionOrderShow({
                                                            production_order:
                                                                qrCode
                                                                    .production_order
                                                                    .id,
                                                        }).url
                                                    }
                                                    className="font-medium text-primary hover:underline"
                                                >
                                                    {
                                                        qrCode.production_order
                                                            .order_number
                                                    }
                                                </Link>
                                            ) : (
                                                <span className="font-medium">
                                                    {
                                                        qrCode.production_order
                                                            .order_number
                                                    }
                                                </span>
                                            )}
                                            <p className="text-xs text-muted-foreground">
                                                Lote:{' '}
                                                {
                                                    qrCode.production_order
                                                        .lot_number
                                                }
                                            </p>
                                            {qrCode.production_order
                                                .completion_date && (
                                                <p className="text-xs text-muted-foreground">
                                                    Completada:{' '}
                                                    {
                                                        qrCode.production_order
                                                            .completion_date
                                                    }
                                                </p>
                                            )}
                                        </div>
                                    ) : (
                                        <p>
                                            <EmptyCell />
                                        </p>
                                    )}
                                </div>
                                <div>
                                    <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        Creado por
                                    </p>
                                    <p className="mt-1 text-sm text-foreground">
                                        {qrCode.created_by?.name ?? (
                                            <EmptyCell />
                                        )}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        Fecha de creación
                                    </p>
                                    <p className="mt-1 text-sm text-foreground">
                                        <FormattedDate
                                            value={qrCode.created_at}
                                            format="long"
                                        />
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Documentos */}
                <section className="space-y-4">
                    <div>
                        <h2 className="text-lg font-semibold">
                            Documentos del lote
                        </h2>
                        <p className="mt-0.5 text-sm text-muted-foreground">
                            Certificados y documentos asociados a este código
                            QR.
                        </p>
                    </div>

                    <DetailTable
                        rows={qrCode.documents}
                        rowKey={(doc) => doc.id}
                        caption="Documentos del lote"
                        columns={[
                            {
                                key: 'file',
                                header: 'Documento',
                                cell: (doc) => (
                                    <>
                                        <p>{doc.file_name}</p>
                                        <Badge
                                            variant={
                                                doc.is_current
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            className="mt-1"
                                        >
                                            {doc.is_current
                                                ? 'Actual'
                                                : 'Anterior'}
                                        </Badge>
                                    </>
                                ),
                                className: 'align-top',
                            },
                            {
                                key: 'type',
                                header: 'Tipo',
                                cell: (doc) => doc.document_type,
                                className: 'align-top text-muted-foreground',
                            },
                            {
                                key: 'version',
                                header: 'Versión',
                                cell: (doc) => `v${doc.version}`,
                                className:
                                    'align-top font-mono text-muted-foreground',
                            },
                            {
                                key: 'uploaded_by',
                                header: 'Subido por',
                                cell: (doc) => doc.uploaded_by?.name ?? null,
                                className: 'align-top text-muted-foreground',
                            },
                            {
                                key: 'date',
                                header: 'Fecha',
                                cell: (doc) =>
                                    doc.created_at ? (
                                        <FormattedDate
                                            value={doc.created_at}
                                            format="short"
                                        />
                                    ) : null,
                                className:
                                    'align-top whitespace-nowrap text-muted-foreground',
                            },
                            {
                                key: 'actions',
                                header: 'Acciones',
                                cell: (doc) => (
                                    <Button variant="outline" size="sm" asChild>
                                        <a
                                            href={doc.download_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <FileText className="size-4" />
                                            Ver PDF
                                        </a>
                                    </Button>
                                ),
                                align: 'right',
                                className: 'align-top',
                            },
                        ]}
                        empty="Este lote aún no cuenta con documentos registrados."
                    />
                </section>
            </div>
        </>
    );
}

// Las migas son la única forma de volver al listado (DESIGN.md §10).
QrCodesShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Códigos QR', href: qrCodesIndex() },
        {
            title: `QR — ${props.qrCode.token}`,
            href: qrCodesShow(props.qrCode.id),
        },
    ],
});
