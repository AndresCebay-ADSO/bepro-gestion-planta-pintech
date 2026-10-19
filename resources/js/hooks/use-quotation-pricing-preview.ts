import { useMemo } from 'react';

import { useServerPreview } from '@/hooks/use-server-preview';
import { toDecimalPayload } from '@/lib/numeric-input';
import { splitPricedRows } from '@/lib/quotation-pricing-rows';
import type {
    PricedRow,
    PricingLine,
    QuotationPricingRow,
} from '@/lib/quotation-pricing-rows';

/** Espera tras la última tecla antes de pedir los precios. */
const PREVIEW_DELAY_MS = 300;

type PreviewRequestBody = {
    iva_percentage: string;
    items: Array<PricedRow['body']>;
};

type PreviewResponse = {
    items: PricingLine[];
    totals: { subtotal: string; iva_amount: string; total: string };
};

/** Por cada fila enviada, en el orden del cuerpo: su `row_key` y su firma. */
type RequestRows = Array<[string, string]>;

export type QuotationPricingPreview = {
    /** Por `row_key`, con la firma de la fila para la que se calculó: las filas que no se pueden cotizar no aparecen. */
    lines: Record<string, PricingLine & { signature: string }>;
    totals: PreviewResponse['totals'];
};

export type QuotationPreviewErrors = {
    /** Por `row_key` y campo: el servidor responde por posición en la petición, no por índice del formulario. */
    byRow: Record<string, Record<string, string>>;
    /** Los que no son de una fila: el IVA o «items» (sin precio de venta, total demasiado grande). */
    general: Record<string, string>;
};

const NO_ERRORS: QuotationPreviewErrors = { byRow: {}, general: {} };

/**
 * Vista previa de precios de la cotización (PR 4b de la auditoría 3.6). El formulario no calcula dinero: le pide al
 * servidor los mismos precios y totales que se guardarían, con la misma regla (`QuotationPricingService`). Así lo que se
 * ve antes de guardar es exactamente lo que queda guardado.
 */
export function useQuotationPricingPreview(
    rows: QuotationPricingRow[],
    ivaPercentage: string,
    previewUrl: string,
) {
    const { request, signatures, incomplete } = useMemo(() => {
        const { priced, incomplete } = splitPricedRows(rows);

        return {
            incomplete,
            request:
                priced.length === 0
                    ? null
                    : {
                          url: previewUrl,
                          body: {
                              iva_percentage: toDecimalPayload(ivaPercentage),
                              items: priced.map(({ body }) => body),
                          },
                          context: priced.map(
                              ({ rowKey, signature }): RequestRows[number] => [
                                  rowKey,
                                  signature,
                              ],
                          ),
                      },
            signatures: Object.fromEntries(
                priced.map(({ rowKey, signature }) => [rowKey, signature]),
            ) as Record<string, string>,
        };
    }, [rows, ivaPercentage, previewUrl]);

    const { result, errors, loading, stale } = useServerPreview<
        PreviewResponse,
        PreviewRequestBody,
        RequestRows
    >(request, PREVIEW_DELAY_MS);

    // Sin filas que cotizar no hay nada que mostrar: se deriva aquí en vez de limpiar el estado dentro del efecto.
    const hasRows = request !== null;

    const preview = useMemo((): QuotationPricingPreview | null => {
        if (!hasRows || result === null) {
            return null;
        }

        const lines: QuotationPricingPreview['lines'] = {};

        result.context.forEach(([rowKey, signature], position) => {
            lines[rowKey] = { ...result.value.items[position], signature };
        });

        return { lines, totals: result.value.totals };
    }, [hasRows, result]);

    const previewErrors = useMemo((): QuotationPreviewErrors => {
        if (!hasRows || errors === null) {
            return NO_ERRORS;
        }

        const byRow: QuotationPreviewErrors['byRow'] = {};
        const general: QuotationPreviewErrors['general'] = {};

        for (const [field, message] of Object.entries(errors.value)) {
            const match = /^items\.(\d+)\.(.+)$/.exec(field);
            const rowKey = match
                ? errors.context[Number(match[1])]?.[0]
                : undefined;

            if (match && rowKey !== undefined) {
                byRow[rowKey] = { ...byRow[rowKey], [match[2]]: message };
            } else {
                general[field] = message;
            }
        }

        return { byRow, general };
    }, [hasRows, errors]);

    /**
     * La línea de la vista previa de una fila y si corresponde a la fila tal como está ahora. Una línea que no está al día
     * (la fila cambió y la respuesta nueva no ha llegado, o fue rechazada) se puede mostrar, pero no como definitiva.
     */
    const lineFor = (rowKey: string) => {
        const line = preview?.lines[rowKey];

        return {
            line,
            isCurrent:
                line !== undefined && line.signature === signatures[rowKey],
        };
    };

    return {
        preview,
        lineFor,
        previewErrors,
        previewLoading: hasRows && loading,
        /** Filas incompletas que no suman en los totales, cuando hay otras que sí se cotizan. */
        previewIncompleteRows: hasRows ? incomplete : 0,
        previewStale: hasRows ? stale : null,
    };
}
