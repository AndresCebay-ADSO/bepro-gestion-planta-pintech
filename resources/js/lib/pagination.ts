import { formatNumber } from '@/lib/formatters';

/**
 * Texto de resumen de un listado paginado:
 * - Listado vacío (sin filas o total <= 0): `null` (ya lo anuncia `<EmptyState>`).
 * - Una sola página (todos los registros caben en ella): «1 registro» o «12 registros».
 * - Varias páginas: «Mostrando 16–30 de 230».
 */
export function paginationSummary(
    from: number | null | undefined,
    to: number | null | undefined,
    total: number | null | undefined,
): string | null {
    if (
        total === null ||
        total === undefined ||
        total <= 0 ||
        from === null ||
        from === undefined ||
        to === null ||
        to === undefined ||
        from <= 0 ||
        to <= 0
    ) {
        return null;
    }

    if (from === 1 && to >= total) {
        return total === 1
            ? '1 registro'
            : `${formatNumber(total, { maxDecimals: 0 })} registros`;
    }

    return `Mostrando ${formatNumber(from, { maxDecimals: 0 })}–${formatNumber(to, { maxDecimals: 0 })} de ${formatNumber(total, { maxDecimals: 0 })}`;
}
