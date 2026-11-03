import { Head, router } from '@inertiajs/react';
import {
    Copy,
    Download,
    ExternalLink,
    FileText,
    QrCode as QrCodeIcon,
} from 'lucide-react';
import { useState } from 'react';

import { ActiveBadge } from '@/components/active-badge';
import { DetailItem, DetailList } from '@/components/detail-list';
import { DetailTable } from '@/components/detail-table';
import { FormattedDate } from '@/components/formatted-date';
import { PageHeader } from '@/components/page-header';
import { RecordLink } from '@/components/record-link';
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
                        <CardContent>
                            <DetailList columns={2}>
                                <DetailItem
                                    label="URL pública"
                                    span="full"
                                    value={
                                        <div className="flex items-center gap-2">
                                            {/* min-w-0: sin él, la URL no se trunca y empuja los botones fuera de la tarjeta. */}
                                            <p
                                                className="min-w-0 flex-1 truncate font-mono text-xs text-foreground"
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
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                asChild
                                            >
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
                                    }
                                />
                                <DetailItem
                                    label="Producto"
                                    value={
                                        qrCode.product && (
                                            <>
                                                {qrCode.product.display_name}
                                                {qrCode.product.description && (
                                                    <div className="mt-1 text-xs whitespace-pre-wrap text-muted-foreground">
                                                        {
                                                            qrCode.product
                                                                .description
                                                        }
                                                    </div>
                                                )}
                                            </>
                                        )
                                    }
                                />
                                <DetailItem
                                    label="Orden de producción"
                                    value={
                                        qrCode.production_order && (
                                            <>
                                                <span className="font-mono">
                                                    {can.viewProductionOrders ? (
                                                        <RecordLink
                                                            href={
                                                                productionOrderShow(
                                                                    {
                                                                        production_order:
                                                                            qrCode
                                                                                .production_order
                                                                                .id,
                                                                    },
                                                                ).url
                                                            }
                                                        >
                                                            {
                                                                qrCode
                                                                    .production_order
                                                                    .order_number
                                                            }
                                                        </RecordLink>
                                                    ) : (
                                                        qrCode.production_order
                                                            .order_number
                                                    )}
                                                </span>
                                                <div className="text-xs text-muted-foreground">
                                                    Lote:{' '}
                                                    <span className="font-mono">
                                                        {
                                                            qrCode
                                                                .production_order
                                                                .lot_number
                                                        }
                                                    </span>
                                                </div>
                                                {qrCode.production_order
                                                    .completion_date && (
                                                    <div className="text-xs text-muted-foreground">
                                                        Completada:{' '}
                                                        <FormattedDate
                                                            value={
                                                                qrCode
                                                                    .production_order
                                                                    .completion_date
                                                            }
                                                            format="date"
                                                        />
                                                    </div>
                                                )}
                                            </>
                                        )
                                    }
                                />
                                <DetailItem
                                    label="Creado por"
                                    value={qrCode.created_by?.name}
                                />
                                <DetailItem
                                    label="Fecha de creación"
                                    value={
                                        <FormattedDate
                                            value={qrCode.created_at}
                                            format="long"
                                        />
                                    }
                                />
                            </DetailList>
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
