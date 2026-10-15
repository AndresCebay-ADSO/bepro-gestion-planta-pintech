/**
 * Campos numéricos de los formularios (auditoría 3.6, INV-15). Una sola fuente para todo el sistema.
 *
 * Regla (decidida el 2026-10-14): el punto y la coma son los dos separador decimal, nunca de miles, y se admite uno
 * solo. Mil doscientos se escribe `1200`; `1.200` es uno coma dos.
 *
 * El formulario guarda lo que el usuario escribió (`"1,5"`) y lo convierte una sola vez al enviar, con
 * `toDecimalPayload`. Nunca se quitan caracteres para que el número «quepa», porque eso cambia su valor sin aviso:
 * - un separador de más (o uno en un campo de enteros) se deja escrito y el campo se marca con error. Si se
 *   bloqueara, los dígitos siguientes caerían en otro lugar: `1.200,5` quedaría en 1,2005 y `3,5` unidades en 35;
 * - lo demás que no cabe (letras, espacios, un decimal o un dígito de más) se rechaza completo, tecla o texto pegado:
 *   el campo conserva lo que tenía.
 *
 * El signo menos solo se admite al principio y en los campos que lo piden (`allowNegative`): el ajuste de precio de una
 * cotización o una temperatura.
 */

export type DecimalLimits = {
    /** Decimales que admite el campo: la escala de su columna (4 en `numeric(12,4)`). */
    maxDecimals?: number;
    /** Dígitos enteros que admite el campo: precisión menos escala (8 en `numeric(12,4)`). */
    maxIntegerDigits?: number;
    /** Si admite un `-` al principio. */
    allowNegative?: boolean;
};

export const DEFAULT_MAX_DECIMALS = 4;
export const DEFAULT_MAX_INTEGER_DIGITS = 8;

type FormValue = string | number | null | undefined;

/** Si el texto, tal como está escrito, puede quedar en el campo: dígitos y a lo sumo un separador (`.` o `,`). */
export function isValidDecimalText(
    text: string,
    {
        maxDecimals = DEFAULT_MAX_DECIMALS,
        maxIntegerDigits = DEFAULT_MAX_INTEGER_DIGITS,
        allowNegative = false,
    }: DecimalLimits = {},
): boolean {
    const integerPart = `${allowNegative ? '-?' : ''}\\d{0,${maxIntegerDigits}}`;
    const pattern =
        maxDecimals > 0
            ? new RegExp(`^${integerPart}(?:[.,]\\d{0,${maxDecimals}})?$`)
            : new RegExp(`^${integerPart}$`);

    return pattern.test(text);
}

/** Si el texto puede quedar en un campo de enteros: solo dígitos. */
export function isValidIntegerText(
    text: string,
    maxDigits: number = DEFAULT_MAX_INTEGER_DIGITS,
): boolean {
    return new RegExp(`^\\d{0,${maxDigits}}$`).test(text);
}

export type NumericTextCheck = 'valid' | 'separator-error' | 'reject';

export const DECIMAL_SEPARATOR_ERROR =
    'Usa un solo separador decimal (punto o coma). Mil doscientos se escribe 1200.';
export const INTEGER_SEPARATOR_ERROR =
    'Solo números enteros, sin punto ni coma.';

const DIGITS_AND_SEPARATORS = /^[\d.,]*$/;
const SIGNED_DIGITS_AND_SEPARATORS = /^-?[\d.,]*$/;

function separatorCount(text: string): number {
    return (text.match(/[.,]/g) ?? []).length;
}

/** Qué hacer con lo que quedó escrito en un campo decimal: aceptarlo, aceptarlo marcado con error, o rechazarlo. */
export function checkDecimalText(
    text: string,
    limits: DecimalLimits = {},
): NumericTextCheck {
    if (isValidDecimalText(text, limits)) {
        return 'valid';
    }

    const allowed = limits.allowNegative
        ? SIGNED_DIGITS_AND_SEPARATORS
        : DIGITS_AND_SEPARATORS;

    return allowed.test(text) && separatorCount(text) > 1
        ? 'separator-error'
        : 'reject';
}

/** Lo mismo para un campo de enteros. */
export function checkIntegerText(
    text: string,
    maxDigits: number = DEFAULT_MAX_INTEGER_DIGITS,
): NumericTextCheck {
    if (isValidIntegerText(text, maxDigits)) {
        return 'valid';
    }

    return DIGITS_AND_SEPARATORS.test(text) && separatorCount(text) > 0
        ? 'separator-error'
        : 'reject';
}

/**
 * Si el campo acepta el cambio. Un valor que ya no cumple las reglas del campo (calculado en pantalla, como un margen
 * negativo, o guardado antes con otra regla) se puede seguir borrando hasta corregirlo: si no, el campo quedaría
 * congelado y solo se podría reescribir seleccionando todo. Lo que queda tiene que ser un número a medio corregir
 * (dígitos, separadores y el signo al principio): las letras se rechazan siempre.
 */
export function acceptsEdit(
    next: string,
    current: string,
    check: (text: string) => NumericTextCheck,
): boolean {
    if (check(next) !== 'reject') {
        return true;
    }

    return (
        check(current) === 'reject' &&
        next.length < current.length &&
        SIGNED_DIGITS_AND_SEPARATORS.test(next)
    );
}

/** Mensaje para un valor que no cabe en el campo decimal (ver `acceptsEdit`). */
export function decimalLimitsMessage({
    maxDecimals = DEFAULT_MAX_DECIMALS,
    maxIntegerDigits = DEFAULT_MAX_INTEGER_DIGITS,
    allowNegative = false,
}: DecimalLimits = {}): string {
    // Corto: también sale bajo campos angostos (el margen en costos).
    const decimals =
        maxDecimals === 0
            ? 'sin decimales'
            : `y ${maxDecimals} ${maxDecimals === 1 ? 'decimal' : 'decimales'}`;

    return `No cabe: hasta ${maxIntegerDigits} enteros ${decimals}${allowNegative ? '' : ', sin negativos'}.`;
}

/** Lo mismo para un campo de enteros. */
export function integerLimitsMessage(
    maxDigits: number = DEFAULT_MAX_INTEGER_DIGITS,
): string {
    return `No cabe: solo enteros, hasta ${maxDigits} dígitos.`;
}

/**
 * Lo que se envía al servidor: la coma pasa a punto, `1,` queda en `1` y `,5` en `0.5` (`-,5` en `-0.5`). El vacío
 * sigue vacío (nunca 0), para que el servidor diga si el campo es obligatorio; un `-` o un separador solos, también.
 * Un texto que no es un número se envía tal cual, para que el servidor lo rechace en vez de cambiarlo aquí sin aviso.
 */
export function toDecimalPayload(value: FormValue): string {
    if (value === null || value === undefined) {
        return '';
    }

    const text = String(value).trim();

    if (!/^-?\d*[.,]?\d*$/.test(text) || !/\d/.test(text)) {
        return /^-?[.,]?$/.test(text) ? '' : text;
    }

    const sign = text.startsWith('-') ? '-' : '';
    const [integerPart, fractionPart = ''] = text
        .slice(sign.length)
        .replace(',', '.')
        .split('.');

    return fractionPart === ''
        ? `${sign}${integerPart}`
        : `${sign}${integerPart === '' ? '0' : integerPart}.${fractionPart}`;
}

/** `toDecimalPayload` en varios campos de un formulario; los demás quedan igual. */
export function withDecimalPayloads<T extends Record<string, unknown>>(
    data: T,
    fields: readonly (keyof T)[],
): T {
    const converted = { ...data };

    for (const field of fields) {
        converted[field] = toDecimalPayload(
            data[field] as FormValue,
        ) as T[keyof T];
    }

    return converted;
}

/** El número escrito, para los cálculos que se muestran en pantalla; `null` si el campo está vacío o no es un número. */
export function parseDecimal(value: FormValue): number | null {
    const payload = toDecimalPayload(value);

    if (!/^-?\d+(?:\.\d+)?$/.test(payload)) {
        return null;
    }

    return Number(payload);
}

/**
 * Aviso para el caso que se presta a confusión: tres dígitos después del separador (`1.200`, `12.500`, `1,250`), que
 * es como se escriben los miles en Colombia. No bloquea el envío: solo confirma lo que se va a registrar.
 */
export function decimalAmbiguityHint(value: FormValue): string | null {
    const text = value === null || value === undefined ? '' : String(value);
    const match = /^(-?)(\d+)([.,])(\d{3})$/.exec(text);

    if (!match || /^0+$/.test(match[2])) {
        return null;
    }

    const [, sign, integerPart, separator, fractionPart] = match;
    const registered = toDecimalPayload(text)
        .replace(/0+$/, '')
        .replace(/\.$/, '')
        .replace('.', ',');
    const separatorName = separator === '.' ? 'punto' : 'coma';

    return `Se registrará ${registered}. Si es un número sin decimales, escríbelo sin ${separatorName}: ${sign}${integerPart}${fractionPart}.`;
}
