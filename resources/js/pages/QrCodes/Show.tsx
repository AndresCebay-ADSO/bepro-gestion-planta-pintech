import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Copy,
    Download,
    ExternalLink,
    FileText,
    QrCode as QrCodeIcon,
} from 'lucide-react';
import { useState } from 'react';

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
        description: string | null;
    } | null;
    production_order: {
        id: number;
        order_number: string;
        lot_number: string | null;
        completion_date: string | null;
        planned_date: string | null;
    } | null;
    documents: QrDocumentItem[];
};

type Props = {
    qrCode: QrCodeDetail;
    can: {
        update: boolean;
    };
};

export default function QrCodesShow({ qrCode, can }: Props) {
    const [copied, setCopied] = useState(false);

    const flash = usePage<{
        flash?: { success?: string; error?: string };
    }>().props.flash;

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
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="sm" asChild>
                        <Link href={qrCodesIndex().url}>
                            <ArrowLeft className="mr-1.5 h-4 w-4" />
                            Volver al listado
                        </Link>
                    </Button>
                </div>

                {flash?.success && (
                    <div className="rounded-md border border-emerald-500/25 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-700 dark:text-emerald-300">
                        {flash.success}
                    </div>
                )}

                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
                            Detalle del QR
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Token:{' '}
                            <span className="font-mono">{qrCode.token}</span>
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge
                            variant={qrCode.is_active ? 'default' : 'secondary'}
                        >
                            {qrCode.is_active ? 'Activo' : 'Inactivo'}
                        </Badge>
                        {can.update && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleToggleActive}
                            >
                                {qrCode.is_active ? 'Desactivar' : 'Activar'}
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* QR Image Card */}
                    <Card className="lg:col-span-1">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
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
                            <CardTitle className="text-base">
                                Información del lote
                            </CardTitle>
                            <CardDescription>
                                Datos del producto y orden de producción
                                asociados.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {/* URL pública */}
                            <div className="rounded-md border border-border bg-background/80 px-3 py-2">
                                <p className="text-[10px] font-medium tracking-wide text-muted-foreground uppercase">
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
                                    <p className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                        Producto
                                    </p>
                                    {qrCode.product ? (
                                        <div className="mt-1">
                                            <p className="font-medium text-foreground">
                                                {qrCode.product.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {qrCode.product.code}
                                            </p>
                                            {qrCode.product.description && (
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {qrCode.product.description}
                                                </p>
                                            )}
                                        </div>
                                    ) : (
                                        <p className="text-muted-foreground">
                                            —
                                        </p>
                                    )}
                                </div>
                                <div>
                                    <p className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                        Orden de Producción
                                    </p>
                                    {qrCode.production_order ? (
                                        <div className="mt-1">
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
                                            {qrCode.production_order
                                                .lot_number && (
                                                <p className="text-xs text-muted-foreground">
                                                    Lote:{' '}
                                                    {
                                                        qrCode.production_order
                                                            .lot_number
                                                    }
                                                </p>
                                            )}
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
                                        <p className="text-muted-foreground">
                                            —
                                        </p>
                                    )}
                                </div>
                                <div>
                                    <p className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                        Creado por
                                    </p>
                                    <p className="mt-1 text-sm text-foreground">
                                        {qrCode.created_by?.name ?? '—'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                        Fecha de creación
                                    </p>
                                    <p className="mt-1 text-sm text-foreground">
                                        {qrCode.created_at
                                            ? new Date(
                                                  qrCode.created_at,
                                              ).toLocaleDateString('es-CO', {
                                                  year: 'numeric',
                                                  month: 'long',
                                                  day: 'numeric',
                                              })
                                            : '—'}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Documents */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <FileText className="h-5 w-5 text-primary" />
                            Documentos del lote
                        </CardTitle>
                        <CardDescription>
                            Certificados y documentos asociados a este código
                            QR.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {qrCode.documents.length === 0 ? (
                            <div className="rounded-xl border border-dashed border-border p-10 text-center">
                                <FileText className="mx-auto mb-3 h-8 w-8 text-slate-300" />
                                <p className="text-sm font-bold text-foreground">
                                    Sin documentos
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Este lote aún no cuenta con documentos
                                    registrados.
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-hidden rounded-lg border border-border">
                                <table className="min-w-full divide-y divide-border text-sm">
                                    <thead className="bg-muted/40">
                                        <tr>
                                            <th className="px-4 py-3 text-left font-medium">
                                                Documento
                                            </th>
                                            <th className="px-4 py-3 text-left font-medium">
                                                Tipo
                                            </th>
                                            <th className="px-4 py-3 text-left font-medium">
                                                Versión
                                            </th>
                                            <th className="px-4 py-3 text-left font-medium">
                                                Subido por
                                            </th>
                                            <th className="px-4 py-3 text-left font-medium">
                                                Fecha
                                            </th>
                                            <th className="px-4 py-3 text-right font-medium">
                                                Acciones
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {qrCode.documents.map((doc) => (
                                            <tr
                                                key={doc.id}
                                                className="hover:bg-muted/30"
                                            >
                                                <td className="px-4 py-3 align-top">
                                                    <p className="font-medium text-foreground">
                                                        {doc.file_name}
                                                    </p>
                                                    {!doc.is_current && (
                                                        <Badge
                                                            variant="outline"
                                                            className="mt-1"
                                                        >
                                                            Anterior
                                                        </Badge>
                                                    )}
                                                    {doc.is_current && (
                                                        <Badge
                                                            variant="default"
                                                            className="mt-1"
                                                        >
                                                            Actual
                                                        </Badge>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 align-top text-muted-foreground">
                                                    {doc.document_type}
                                                </td>
                                                <td className="px-4 py-3 align-top font-mono text-xs text-muted-foreground">
                                                    v{doc.version}
                                                </td>
                                                <td className="px-4 py-3 align-top text-muted-foreground">
                                                    {doc.uploaded_by?.name ??
                                                        '—'}
                                                </td>
                                                <td className="px-4 py-3 align-top text-muted-foreground">
                                                    {doc.created_at ?? '—'}
                                                </td>
                                                <td className="px-4 py-3 text-right align-top">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <a
                                                            href={
                                                                doc.download_url
                                                            }
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                        >
                                                            <Download className="mr-1.5 h-4 w-4" />
                                                            Descargar
                                                        </a>
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
