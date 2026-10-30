import { formatNumber } from '@/lib/formatters';

export interface PaginationNoun {
    one: string;
    other: string;
}

const DEFAULT_NOUN: PaginationNoun = {
    one: 'registro',
    other: 'registros',
};

/**
 * Texto de resumen de un listado paginado:
 * - Listado vacío (`total <= 0`): `null` (ya lo anuncia `<EmptyState>`).
 * - Una sola página o página fuera de rango (`!from || !to`): «1 registro» o «12 registros».
 * - Varias páginas: «Mostrando 16–30 de 230 registros».
 */
export function paginationSummary(
    from: number | null | undefined,
    to: number | null | undefined,
    total: number | null | undefined,
    noun: PaginationNoun = DEFAULT_NOUN,
): string | null {
    if (!total || total <= 0) {
        return null;
    }

    const label = total === 1 ? noun.one : noun.other;

    if (!from || !to || (from === 1 && to >= total)) {
        return `${formatNumber(total, { maxDecimals: 0 })} ${label}`;
    }

    return `Mostrando ${formatNumber(from, { maxDecimals: 0 })}–${formatNumber(to, { maxDecimals: 0 })} de ${formatNumber(total, { maxDecimals: 0 })} ${label}`;
}
