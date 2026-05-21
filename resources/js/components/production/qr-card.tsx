import { Copy, Download, ExternalLink, QrCode } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { ProductionOrder } from '@/types/production-orders';

type QrCardProps = {
    order: ProductionOrder;
    landingFullUrl: string;
    landingLinkCopied: boolean;
    onCopyLandingLink: () => void;
};

export function QrCard({
    order,
    landingFullUrl,
    landingLinkCopied,
    onCopyLandingLink,
}: QrCardProps) {
    return (
        <Card className="border-primary/25 bg-gradient-to-b from-primary/5 to-card shadow-sm">
            <CardHeader className="pb-2">
                <CardTitle className="flex items-center gap-2 text-base">
                    <QrCode className="h-5 w-5 text-primary" />
                    Documentación pública del lote
                </CardTitle>
                <CardDescription>
                    Enlace seguro por orden. Muestra datos del lote, certificado
                    de calidad y PDFs del producto (ficha técnica, hoja de
                    seguridad) cuando estén cargados.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                {order.qr_image_url && (
                    <div className="flex justify-center rounded-lg border border-border bg-white p-4">
                        <img
                            src={order.qr_image_url}
                            alt={`Código QR del lote ${order.order_number}`}
                            width={160}
                            height={160}
                            className="block"
                        />
                    </div>
                )}
                <div className="rounded-md border border-border bg-background/80 px-3 py-2">
                    <p className="text-[10px] font-medium tracking-wide text-muted-foreground uppercase">
                        URL pública
                    </p>
                    <p
                        className="truncate font-mono text-xs text-foreground"
                        title={landingFullUrl}
                    >
                        {landingFullUrl}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button variant="default" size="sm" asChild>
                        <a
                            href={order.qr_landing_url ?? '#'}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <ExternalLink className="mr-1.5 h-4 w-4" />
                            Abrir landing
                        </a>
                    </Button>
                    {order.qr_image_url && (
                        <Button variant="outline" size="sm" asChild>
                            <a
                                href={order.qr_image_url}
                                download={`qr-lote-${order.order_number}.png`}
                            >
                                <Download className="mr-1.5 h-4 w-4" />
                                Descargar QR
                            </a>
                        </Button>
                    )}
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={onCopyLandingLink}
                    >
                        <Copy className="mr-1.5 h-4 w-4" />
                        {landingLinkCopied ? 'Copiado' : 'Copiar enlace'}
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}
