import { useEffect, useMemo, useState } from 'react';

import { toDecimalPayload } from '@/lib/numeric-input';

export type QuotationPricingItem = {
    product_id: string;
    product_variant_id: string;
    quantity: string;
    price_adjustment_pct: string;
    unit_price: string;
};

type PreviewLine = {
    list_unit_price: string;
    price_adjustment_pct: string;
    unit_price: string;
    subtotal: string;
};

export type QuotationPricingPreview = {
    /** Por índice de la fila del formulario: las filas incompletas no se envían y no aparecen. */
    lines: Record<number, PreviewLine>;
    totals: { subtotal: string; iva_amount: string; total: string };
};

/** Una fila se cotiza cuando tiene presentación y una cantidad entera mayor que cero. */
function isComplete(item: QuotationPricingItem): boolean {
    return (
        item.product_id !== '' &&
        item.product_variant_id !== '' &&
        /^\d+$/.test(item.quantity) &&
        Number(item.quantity) > 0
    );
}

/**
 * Vista previa de precios de la cotización (PR 4b de la auditoría 3.6). El formulario no calcula dinero: espera a que
 * el usuario deje de escribir y le pide al servidor los mismos precios y totales que se guardarían, con la misma regla
 * (`QuotationPricingService`). Así lo que se ve antes de guardar es exactamente lo que queda guardado.
 */
export function useQuotationPricingPreview(
    items: QuotationPricingItem[],
    ivaPercentage: string,
    previewUrl: string,
) {
    const [preview, setPreview] = useState<QuotationPricingPreview | null>(
        null,
    );
    const [previewLoading, setPreviewLoading] = useState(false);
    const [previewStale, setPreviewStale] = useState<
        'invalid' | 'failed' | null
    >(null);

    // Solo cambia cuando cambia algo que afecta el precio: la descripción o el color no piden otra vista previa.
    const signature = useMemo(
        () =>
            JSON.stringify({
                iva: ivaPercentage,
                rows: items.flatMap((item, index) =>
                    isComplete(item)
                        ? [
                              [
                                  index,
                                  item.product_id,
                                  item.product_variant_id,
                                  item.quantity,
                                  item.price_adjustment_pct,
                                  item.unit_price,
                              ],
                          ]
                        : [],
                ),
            }),
        [items, ivaPercentage],
    );

    useEffect(() => {
        const { iva, rows } = JSON.parse(signature) as {
            iva: string;
            rows: Array<[number, string, string, string, string, string]>;
        };

        if (rows.length === 0) {
            return;
        }

        // fetch no envía el XSRF-TOKEN por su cuenta, como sí hace Axios.
        const match = document.cookie.match(/(^| )XSRF-TOKEN=([^;]+)/);
        const xsrfToken = match ? decodeURIComponent(match[2]) : '';
        const controller = new AbortController();
        let loadingIndicatorId: number | null = null;

        const timeoutId = window.setTimeout(async () => {
            loadingIndicatorId = window.setTimeout(
                () => setPreviewLoading(true),
                180,
            );

            try {
                const response = await fetch(previewUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': xsrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        iva_percentage: toDecimalPayload(iva),
                        items: rows.map(
                            ([
                                ,
                                productId,
                                variantId,
                                quantity,
                                adjustment,
                                unitPrice,
                            ]) => ({
                                product_id: productId,
                                product_variant_id: variantId,
                                quantity,
                                price_adjustment_pct:
                                    toDecimalPayload(adjustment) || null,
                                unit_price: unitPrice || null,
                            }),
                        ),
                    }),
                    signal: controller.signal,
                });

                if (!response.ok) {
                    setPreviewStale(
                        response.status === 422 ? 'invalid' : 'failed',
                    );

                    return;
                }

                const payload = (await response.json()) as {
                    items: PreviewLine[];
                    totals: QuotationPricingPreview['totals'];
                };
                const lines: Record<number, PreviewLine> = {};

                rows.forEach(([index], position) => {
                    lines[index] = payload.items[position];
                });

                setPreview({ lines, totals: payload.totals });
                setPreviewStale(null);
            } catch (error) {
                // Una petición cancelada (el usuario siguió escribiendo) no es un fallo: la reemplaza la siguiente.
                if ((error as Error).name !== 'AbortError') {
                    setPreviewStale('failed');
                }
            } finally {
                if (loadingIndicatorId !== null) {
                    window.clearTimeout(loadingIndicatorId);
                }

                setPreviewLoading(false);
            }
        }, 300);

        return () => {
            controller.abort();

            if (loadingIndicatorId !== null) {
                window.clearTimeout(loadingIndicatorId);
            }

            window.clearTimeout(timeoutId);
            setPreviewLoading(false);
        };
    }, [previewUrl, signature]);

    // Sin filas completas no hay nada que mostrar: se deriva aquí en vez de limpiar el estado dentro del efecto.
    const hasRows = signature.includes('[[');

    return {
        preview: hasRows ? preview : null,
        previewLoading: hasRows && previewLoading,
        previewStale: hasRows ? previewStale : null,
    };
}
