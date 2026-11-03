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
    emptyValue?: string;
    /** Eliminar ceros al final (útil para vistas de detalle) */
    trimTrailingZeros?: boolean;
}

export type NumberKind =
    'quantity' | 'units' | 'clientPrice' | 'cost' | 'unitCost' | 'percent';

export interface NumberKindRule {
    currency?: string;
    maxDecimals: number;
    trimTrailingZeros: boolean;
    isPercent?: boolean;
}

export const NUMBER_KINDS: Record<NumberKind, NumberKindRule> = {
    quantity: { maxDecimals: 4, trimTrailingZeros: true },
    units: { maxDecimals: 0, trimTrailingZeros: false },
    clientPrice: { currency: '$', maxDecimals: 0, trimTrailingZeros: false },
    cost: { currency: '$', maxDecimals: 2, trimTrailingZeros: true },
    unitCost: { currency: '$', maxDecimals: 4, trimTrailingZeros: true },
    percent: { maxDecimals: 2, trimTrailingZeros: true, isPercent: true },
};

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
 * Elimina ceros al final de la parte decimal y la coma si no queda decimal
 * Ej: "100,9300" → "100,93", "100,0000" → "100"
 */
function trimTrailingZerosFromFormatted(formatted: string): string {
    // Si no tiene coma, no hay decimales que procesar
    if (!formatted.includes(',')) {
        return formatted;
    }

    // Eliminar ceros al final
    let result = formatted.replace(/0+$/, '');

    // Si terminó en coma, eliminar la coma también
    if (result.endsWith(',')) {
        result = result.slice(0, -1);
    }

    return result;
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
 * Indica si un valor numérico está vacío o no es un número válido (mismo análisis que `formatNumber`).
 */
export function isEmptyNumber(
    value: number | string | null | undefined,
): boolean {
    if (value === null || value === undefined || value === '') {
        return true;
    }

    return parseToNumber(value) === null;
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
        emptyValue = '—',
        trimTrailingZeros = false,
    } = options;

    // Vacíos: nunca llevan símbolo de moneda («Sin precio», no «$Sin precio»)
    if (value === null || value === undefined || value === '') {
        return emptyValue;
    }

    const numValue = parseToNumber(value);

    if (numValue === null) {
        return emptyValue;
    }

    const formatter = getFormatter(maxDecimals);
    let formatted = formatter.format(Math.abs(numValue));

    // Eliminar ceros al final si está habilitado (para vistas de detalle)
    if (trimTrailingZeros) {
        formatted = trimTrailingZerosFromFormatted(formatted);
    }

    // Manejo correcto de negativos: lo que redondea a cero no lleva signo («0», no «-0»), como en NumberFormat de PHP
    const sign = numValue < 0 && /[1-9]/.test(formatted) ? '-' : '';

    return `${sign}${currency ?? ''}${formatted}`;
}

/**
 * Moneda (usa la base optimizada)
 */
export function formatCurrency(
    value: number | string | null | undefined,
    currencySymbol: string = '$',
    options: Omit<FormatNumberOptions, 'currency'> = {},
): string {
    return formatNumber(value, { ...options, currency: currencySymbol });
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
 * Porcentaje en puntos porcentuales (ej.: 19 → «19%», 19.5 → «19,5%»)
 */
export function formatPercent(
    value: number | string | null | undefined,
    maxDecimals: number = 2,
    emptyValue: string = '—',
    trimTrailingZeros: boolean = true,
): string {
    // El vacío no lleva «%»: se decide antes de formatear.
    if (isEmptyNumber(value)) {
        return emptyValue;
    }

    return `${formatNumber(value, { maxDecimals, trimTrailingZeros })}%`;
}

/**
 * Formatea un valor según el tipo de número aprobado para Pintech OS:
 * - quantity: hasta 4 decimales, sin ceros finales
 * - units: entero
 * - clientPrice: $, entero
 * - cost: $, hasta 2 decimales, sin ceros finales
 * - unitCost: $, hasta 4 decimales, sin ceros finales
 * - percent: hasta 2 decimales, sin ceros finales, pegado
 */
export function formatKind(
    value: number | string | null | undefined,
    kind: NumberKind,
    emptyValue: string = '—',
): string {
    const rule = NUMBER_KINDS[kind];

    if (rule.isPercent) {
        return formatPercent(value, rule.maxDecimals, emptyValue);
    }

    return formatNumber(value, {
        currency: rule.currency,
        maxDecimals: rule.maxDecimals,
        trimTrailingZeros: rule.trimTrailingZeros,
        emptyValue,
    });
}

/**
 * Valor guardado listo para un campo de formulario: el decimal de la base (`"1500.5000"`) sin los ceros finales
 * (`"1500.5"`). Trabaja sobre el texto, sin pasar por un float, así que no pierde dígitos: el dinero llega a 16 cifras
 * (`numeric(16,4)`). Sin esto, un campo de 2 decimales recibía `1500.0000`, lo marcaba en error y el servidor lo
 * rechazaba (revisión del PR 5 de la auditoría 3.6: entrada a un lote existente).
 */
export function formatForInput(
    value: number | string | null | undefined,
): string {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    if (typeof value === 'number') {
        return Number.isFinite(value) ? String(value) : '';
    }

    const match = /^([-+]?)(\d*)(?:\.(\d*))?$/.exec(value.trim());

    if (match === null || !/\d/.test(`${match[2]}${match[3] ?? ''}`)) {
        return '';
    }

    const integerPart = match[2].replace(/^0+(?=\d)/, '') || '0';
    const fraction = (match[3] ?? '').replace(/0+$/, '');
    const text = fraction === '' ? integerPart : `${integerPart}.${fraction}`;

    return match[1] === '-' && text !== '0' ? `-${text}` : text;
}

/**
 * Contador de una insignia (campana, menú lateral): hasta 9 se muestra el número; desde 10, «9+». Un solo formato para
 * que la campana y el menú no digan cosas distintas en la misma pantalla.
 */
export function formatBadgeCount(count: number): string {
    return count > 9 ? '9+' : String(count);
}
