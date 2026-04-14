/**
 * Utilidades de formateo de números para Pintech OS
 * Estándar Colombia: puntos para miles, coma para decimales
 *
 * ✔ Optimizado (memoización de Intl)
 * ✔ DRY (validaciones centralizadas)
 * ✔ Seguro (mejor parsing)
 * ✔ Escalable (reutilizable en toda la app)
 */

export interface FormatNumberOptions {
    currency?: string;
    maxDecimals?: number;
    alwaysShowCurrency?: boolean;
    emptyValue?: string;
}

/**
 * Cache de formatters para evitar recrearlos (performance 🚀)
 */
const formatterCache = new Map<string, Intl.NumberFormat>();

function getFormatter(maxDecimals: number): Intl.NumberFormat {
    const key = `es-CO-${maxDecimals}`;

    if (!formatterCache.has(key)) {
        formatterCache.set(
            key,
            new Intl.NumberFormat('es-CO', {
                minimumFractionDigits: 0,
                maximumFractionDigits: maxDecimals,
            }),
        );
    }

    return formatterCache.get(key)!;
}

/**
 * Parsing seguro (evita basura tipo "123abc")
 */
function parseToNumber(value: number | string): number | null {
    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : null;
    }

    if (typeof value === 'string') {
        const trimmed = value.trim();

        // Validación estricta (solo números válidos)
        if (!/^[-+]?\d*\.?\d+$/.test(trimmed)) {
            return null;
        }

        const parsed = Number(trimmed);

        return Number.isFinite(parsed) ? parsed : null;
    }

    return null;
}

/**
 * Manejo centralizado de valores vacíos
 */
function handleEmpty(
    currency: string | undefined,
    alwaysShowCurrency: boolean,
    emptyValue: string,
): string {
    return alwaysShowCurrency && currency
        ? `${currency}${emptyValue}`
        : emptyValue;
}

/**
 * Formatea un número según estándar colombiano
 */
export function formatNumber(
    value: number | string | null | undefined,
    options: FormatNumberOptions = {},
): string {
    const {
        currency,
        maxDecimals = 4,
        alwaysShowCurrency = false,
        emptyValue = '-',
    } = options;

    // Vacíos
    if (value === null || value === undefined || value === '') {
        return handleEmpty(currency, alwaysShowCurrency, emptyValue);
    }

    const numValue = parseToNumber(value);

    if (numValue === null) {
        return handleEmpty(currency, alwaysShowCurrency, emptyValue);
    }

    const formatter = getFormatter(maxDecimals);
    const formatted = formatter.format(Math.abs(numValue));

    // Manejo correcto de negativos
    if (currency) {
        return numValue < 0
            ? `-${currency}${formatted}`
            : `${currency}${formatted}`;
    }

    return numValue < 0 ? `-${formatted}` : formatted;
}

/**
 * Moneda (usa la base optimizada)
 */
export function formatCurrency(
    value: number | string | null | undefined,
    currencySymbol: string = '$',
    options: Omit<FormatNumberOptions, 'currency' | 'alwaysShowCurrency'> = {},
): string {
    return formatNumber(value, {
        ...options,
        currency: currencySymbol,
        alwaysShowCurrency: true,
    });
}

/**
 * Cantidades (stock, peso, etc.)
 */
export function formatQuantity(
    value: number | string | null | undefined,
    options: Omit<FormatNumberOptions, 'currency'> = {},
): string {
    return formatNumber(value, options);
}

/**
 * Porcentaje (reutiliza lógica base 🚀)
 */
export function formatPercent(
    value: number | string | null | undefined,
    maxDecimals: number = 2,
): string {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    const numValue = parseToNumber(value);

    if (numValue === null) {
        return '-';
    }

    return `${formatNumber(numValue * 100, { maxDecimals })}%`;
}

/**
 * Formato para inputs (React / forms)
 */
export function formatForInput(
    value: number | string | null | undefined,
): string {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    const numValue = parseToNumber(value);

    return numValue !== null ? numValue.toString() : '';
}
