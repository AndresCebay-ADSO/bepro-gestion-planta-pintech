/**
 * Campos numéricos de los formularios (auditoría 3.6, INV-15). Una sola fuente para todo el sistema.
 *
 * Regla (decidida el 2026-10-14): el punto y la coma son los dos separador decimal, nunca de miles, y se admite uno
 * solo. Mil doscientos se escribe `1200`; `1.200` es uno coma dos. Los puntos de miles los pone el campo en lo que se
 * ve (`1.200,5`), nunca el usuario: el punto que se teclea entra como coma decimal.
 *
 * El formulario guarda lo que el usuario escribió, sin miles (`"1200,5"`), y lo convierte una sola vez al enviar, con
 * `toDecimalPayload`. Nunca se quitan caracteres para que el número «quepa», porque eso cambia su valor sin aviso:
 * - un separador de más (o uno en un campo de enteros) se deja escrito y el campo se marca con error. Si se
 *   bloqueara, los dígitos siguientes caerían en otro lugar: `1.200,5` quedaría en 1,2005 y `3,5` unidades en 35;
 * - lo demás que no cabe (letras, espacios, un decimal o un dígito de más) se rechaza completo, tecla o texto pegado:
 *   el campo conserva lo que tenía.
 *
 * El signo menos solo se admite al principio y en los campos que lo piden (`allowNegative`): el ajuste de precio de una
 * cotización o una temperatura.
 */

import { NUMBER_TYPES } from '@/lib/number-types';

export type DecimalLimits = {
    /** Decimales que admite el campo: la escala de su columna (4 en `numeric(12,4)`). */
    maxDecimals?: number;
    /** Dígitos enteros que admite el campo: precisión menos escala (8 en `numeric(12,4)`). */
    maxIntegerDigits?: number;
    /** Si admite un `-` al principio. */
    allowNegative?: boolean;
};

/** Sin límites propios, un campo admite lo de una Cantidad (`numeric(12,4)`), el número más común del sistema. */
export const DEFAULT_MAX_DECIMALS = NUMBER_TYPES.quantity.maxDecimals;
export const DEFAULT_MAX_INTEGER_DIGITS =
    NUMBER_TYPES.quantity.maxIntegerDigits;

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
    'Usa un solo separador decimal. El punto de miles lo pone el campo: mil doscientos se escribe 1200.';
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
 * es como se escriben los miles en Colombia. No bloquea el envío: solo confirma lo que se va a registrar. El campo ya
 * muestra `1,250`, pero quien escribe rápido no mira el campo; por eso el número se nombra en palabras.
 */
export function decimalAmbiguityHint(value: FormValue): string | null {
    const text = value === null || value === undefined ? '' : String(value);
    const match = /^(-?)(\d+)[.,](\d{3})$/.exec(text);

    if (!match || /^0+$/.test(match[2])) {
        return null;
    }

    const [, sign, integerPart, fractionPart] = match;
    const registered = toDecimalPayload(text)
        .replace(/0+$/, '')
        .replace(/\.$/, '')
        .replace('.', ',');
    const words = `${sign ? 'menos ' : ''}${integerInWords(integerPart + fractionPart)}`;

    return `Se registrará ${registered}. Si es ${words}, escríbelo sin punto ni coma: ${sign}${integerPart}${fractionPart}.`;
}

const UNITS_IN_WORDS = [
    '',
    'uno',
    'dos',
    'tres',
    'cuatro',
    'cinco',
    'seis',
    'siete',
    'ocho',
    'nueve',
    'diez',
    'once',
    'doce',
    'trece',
    'catorce',
    'quince',
    'dieciséis',
    'diecisiete',
    'dieciocho',
    'diecinueve',
    'veinte',
    'veintiuno',
    'veintidós',
    'veintitrés',
    'veinticuatro',
    'veinticinco',
    'veintiséis',
    'veintisiete',
    'veintiocho',
    'veintinueve',
];
const TENS_IN_WORDS = [
    '',
    '',
    '',
    'treinta',
    'cuarenta',
    'cincuenta',
    'sesenta',
    'setenta',
    'ochenta',
    'noventa',
];
const HUNDREDS_IN_WORDS = [
    '',
    'ciento',
    'doscientos',
    'trescientos',
    'cuatrocientos',
    'quinientos',
    'seiscientos',
    'setecientos',
    'ochocientos',
    'novecientos',
];
/** Millones y billones (10¹²), en singular y plural. */
const LARGE_SCALES_IN_WORDS = [
    ['', ''],
    ['millón', 'millones'],
    ['billón', 'billones'],
];

/** De 1 a 999. Delante de «mil» o «millones», `uno` se acorta: «veintiún mil», «treinta y un millones». */
function hundredsInWords(number: number, beforeScale: boolean): string {
    if (number === 100) {
        return 'cien';
    }

    const rest = number % 100;
    let restWords =
        rest < 30
            ? UNITS_IN_WORDS[rest]
            : `${TENS_IN_WORDS[Math.floor(rest / 10)]}${rest % 10 ? ` y ${UNITS_IN_WORDS[rest % 10]}` : ''}`;

    if (beforeScale) {
        restWords = restWords
            .replace(/veintiuno$/, 'veintiún')
            .replace(/uno$/, 'un');
    }

    return [HUNDREDS_IN_WORDS[Math.floor(number / 100)], restWords]
        .filter(Boolean)
        .join(' ');
}

/** De 1 a 999.999. */
function thousandsInWords(number: number, beforeScale: boolean): string {
    const thousands = Math.floor(number / 1000);
    const rest = number % 1000;
    const thousandsWords =
        thousands === 0
            ? ''
            : thousands === 1
              ? 'mil'
              : `${hundredsInWords(thousands, true)} mil`;

    return [thousandsWords, rest ? hundredsInWords(rest, beforeScale) : '']
        .filter(Boolean)
        .join(' ');
}

/**
 * Un entero en palabras (`1250` → «mil doscientos cincuenta»), para los avisos. Trabaja por grupos de seis dígitos del
 * texto, nunca con el número entero en un float. Más de 18 dígitos (no cabe en ningún campo) se devuelve en cifras.
 */
export function integerInWords(digits: string): string {
    const significant = digits.replace(/^0+/, '');

    if (significant === '') {
        return 'cero';
    }

    const groups: number[] = [];

    for (let end = significant.length; end > 0; end -= 6) {
        groups.unshift(Number(significant.slice(Math.max(0, end - 6), end)));
    }

    if (groups.length > LARGE_SCALES_IN_WORDS.length) {
        return significant;
    }

    return groups
        .map((group, index) => {
            const scale = groups.length - 1 - index;

            if (group === 0) {
                return '';
            }

            if (scale === 0) {
                return thousandsInWords(group, false);
            }

            const [singular, plural] = LARGE_SCALES_IN_WORDS[scale];

            return group === 1
                ? `un ${singular}`
                : `${thousandsInWords(group, true)} ${plural}`;
        })
        .filter(Boolean)
        .join(' ');
}

/*
 * Miles mientras se escribe. El formulario guarda el texto sin miles; el campo muestra el mismo número agrupado
 * (`1200,5` se ve `1.200,5`) y, en cada cambio, traduce lo que se tecleó, borró o pegó en lo que se ve al texto
 * guardado. Un texto con error (dos separadores) o que no cabe (llegó del servidor) se ve tal cual, sin agrupar.
 */

/** Lo que un campo numérico necesita para mostrar y editar su texto. */
export type NumericField = {
    check: (text: string) => NumericTextCheck;
    /** Lo que se ve: el texto agrupado si es válido; si no, tal cual. */
    format: (text: string) => string;
    /** Si el punto tecleado o pegado es decimal y entra como coma (campos decimales; en los de enteros es un error). */
    dotIsDecimal: boolean;
};

/** Lo que queda después de un cambio: el texto que guarda el formulario, lo que se ve y dónde queda el cursor. */
export type NumericEdit = {
    value: string;
    display: string;
    caret: number;
};

const THOUSANDS_DOT = '.';

function groupThousands(digits: string): string {
    return digits.replace(/\B(?=(\d{3})+(?!\d))/g, THOUSANDS_DOT);
}

/** Lo que se ve en un campo decimal: `1200.5` → `1.200,5`, `,5` → `,5`, `1200,` → `1.200,`. Con error, tal cual. */
export function formatDecimalDisplay(
    text: string,
    limits: DecimalLimits = {},
): string {
    if (checkDecimalText(text, limits) !== 'valid') {
        return text;
    }

    const [, sign, integerPart, separator, fractionPart] =
        /^(-?)(\d*)([.,]?)(\d*)$/.exec(text) ?? [];

    return `${sign}${groupThousands(integerPart)}${separator ? `,${fractionPart}` : ''}`;
}

/** Lo que se ve en un campo de enteros: `1200` → `1.200`. Con error, tal cual. */
export function formatIntegerDisplay(
    text: string,
    maxDigits: number = DEFAULT_MAX_INTEGER_DIGITS,
): string {
    return checkIntegerText(text, maxDigits) === 'valid'
        ? groupThousands(text)
        : text;
}

/** Quita el formato de lo que se ve agrupado: `1.200,5` → `1200,5`. Solo para un texto que el campo agrupó. */
export function stripThousandsDots(display: string): string {
    return display.replaceAll(THOUSANDS_DOT, '');
}

/**
 * Lo pegado (o tecleado) en un campo numérico, sin formato. Se quitan los espacios y el `$`. Si hay punto y coma, el
 * último es el decimal y los demás son miles (`1.250.000,75` → `1250000,75`); si se repite el mismo separador, son
 * miles (`1.250.000` → `1250000`); uno solo es decimal, igual que al teclear (`1.250` es uno coma veinticinco). Lo
 * demás (letras) se deja para que el campo lo rechace completo.
 */
export function normalizeNumericInsertion(text: string): string {
    const compact = text.replace(/[\s$]/g, '');
    const separators = compact.match(/[.,]/g) ?? [];

    if (separators.length < 2) {
        return compact;
    }

    if (new Set(separators).size === 1) {
        return compact.replace(/[.,]/g, '');
    }

    const decimalAt = Math.max(
        compact.lastIndexOf('.'),
        compact.lastIndexOf(','),
    );

    return `${compact.slice(0, decimalAt).replace(/[.,]/g, '')}${compact.slice(decimalAt)}`;
}

export function decimalField(limits: DecimalLimits = {}): NumericField {
    return {
        check: (text) => checkDecimalText(text, limits),
        format: (text) => formatDecimalDisplay(text, limits),
        dotIsDecimal: true,
    };
}

export function integerField(
    maxDigits: number = DEFAULT_MAX_INTEGER_DIGITS,
): NumericField {
    return {
        check: (text) => checkIntegerText(text, maxDigits),
        format: (text) => formatIntegerDisplay(text, maxDigits),
        dotIsDecimal: false,
    };
}

/** Dónde queda el cursor en lo que se ve: después de `count` caracteres del texto guardado, antes del punto de miles. */
function displayCaret(
    field: NumericField,
    value: string,
    display: string,
    count: number,
): number {
    if (field.check(value) !== 'valid' || count <= 0) {
        return Math.max(0, count);
    }

    let seen = 0;

    for (let index = 0; index < display.length; index++) {
        if (display[index] !== THOUSANDS_DOT && ++seen === count) {
            return index + 1;
        }
    }

    return display.length;
}

/**
 * Reemplaza lo que se ve entre `start` y `end` por `text` (lo tecleado o pegado, o nada al borrar) y lo aplica al texto
 * guardado: cada carácter que se ve, salvo los puntos de miles, es uno del texto guardado. Lo insertado se normaliza
 * con `normalizeNumericInsertion` y, en un campo decimal, su punto entra como coma.
 *
 * Borrar solo un punto de miles (`inputType` `deleteContentBackward` o `deleteContentForward`) salta el punto y borra el
 * dígito de al lado: el anterior con la tecla de borrar, el siguiente con Supr. Si el campo no acepta el cambio
 * (`acceptsEdit`), el texto queda como estaba y el cursor al final del tramo.
 */
export function replaceNumericRange(
    field: NumericField,
    current: string,
    start: number,
    end: number,
    text: string,
    inputType: string = '',
): NumericEdit {
    const shown = field.format(current);
    const isGrouped = field.check(current) === 'valid';
    const toValueIndex = (index: number) =>
        isGrouped ? stripThousandsDots(shown.slice(0, index)).length : index;
    const normalized = normalizeNumericInsertion(text);
    const inserted = field.dotIsDecimal
        ? normalized.replaceAll('.', ',')
        : normalized;
    let valueStart = toValueIndex(start);
    let valueEnd = toValueIndex(end);

    if (
        isGrouped &&
        inserted === '' &&
        shown.slice(start, end) === THOUSANDS_DOT
    ) {
        if (inputType === 'deleteContentBackward') {
            valueStart = Math.max(0, valueStart - 1);
        } else if (inputType === 'deleteContentForward') {
            valueEnd++;
        }
    }

    const value = `${current.slice(0, valueStart)}${inserted}${current.slice(valueEnd)}`;

    if (!acceptsEdit(value, current, field.check)) {
        return { value: current, display: shown, caret: end };
    }

    const display = field.format(value);

    return {
        value,
        display,
        caret: displayCaret(
            field,
            value,
            display,
            valueStart + inserted.length,
        ),
    };
}

/**
 * Un cambio ya hecho en el campo: `next` y `caret` son lo que quedó escrito y dónde quedó el cursor después de teclear o
 * borrar. Lo que cambió se busca comparando con lo que se veía: lo de después del cursor quedó igual, y lo de antes,
 * hasta donde coincide. Con lo que se teclea o se borra sin selección, eso da el tramo exacto; lo pegado sobre una
 * selección no siempre (si empieza igual que lo que reemplaza), por eso lo pegado va por `replaceNumericRange`.
 */
export function applyNumericEdit(
    field: NumericField,
    current: string,
    next: string,
    caret: number,
    inputType: string = '',
): NumericEdit {
    const shown = field.format(current);
    let suffix = 0;
    const maxSuffix = Math.min(next.length - caret, shown.length);

    while (
        suffix < maxSuffix &&
        shown[shown.length - 1 - suffix] === next[next.length - 1 - suffix]
    ) {
        suffix++;
    }

    let prefix = 0;
    const maxPrefix = Math.min(shown.length, next.length) - suffix;

    while (prefix < maxPrefix && shown[prefix] === next[prefix]) {
        prefix++;
    }

    return replaceNumericRange(
        field,
        current,
        prefix,
        shown.length - suffix,
        next.slice(prefix, next.length - suffix),
        inputType,
    );
}

/** Deja en el campo del DOM lo que se ve y el cursor, y devuelve el texto que guarda el formulario. */
function showNumericEdit(input: HTMLInputElement, edit: NumericEdit): string {
    // React no vuelve a escribir el valor si ya coincide con el suyo: el cursor queda donde se pone aquí.
    input.value = edit.display;

    if (input.ownerDocument.activeElement === input) {
        input.setSelectionRange(edit.caret, edit.caret);
    }

    return edit.value;
}

/**
 * `applyNumericEdit` dentro del `onChange` del campo. El cambio se lee del evento `input` (lo que quedó, el cursor y su
 * `inputType`), nunca de las teclas: en los teclados de Android, `keydown` llega como `Unidentified`.
 */
export function applyNumericInput(
    input: HTMLInputElement,
    nativeEvent: Event,
    field: NumericField,
    current: string,
): string {
    const inputType =
        'inputType' in nativeEvent ? String(nativeEvent.inputType) : '';

    return showNumericEdit(
        input,
        applyNumericEdit(
            field,
            current,
            input.value,
            input.selectionStart ?? input.value.length,
            inputType,
        ),
    );
}

/** `replaceNumericRange` con lo pegado sobre la selección, dentro del `onPaste` del campo (que cancela el pegado). */
export function applyNumericPaste(
    input: HTMLInputElement,
    pasted: string,
    field: NumericField,
    current: string,
): string {
    const end = input.selectionEnd ?? input.value.length;

    return showNumericEdit(
        input,
        replaceNumericRange(
            field,
            current,
            input.selectionStart ?? end,
            end,
            pasted,
            'insertFromPaste',
        ),
    );
}

/**
 * El ID de un campo de selección tal como viaja al servidor: un número cuando es un entero seguro, `null` cuando no se
 * eligió nada, y el texto tal cual si no es un entero (para que el servidor lo rechace). Nunca `Number('')`, que es 0 y
 * hacía responder «seleccionado no es válido» en vez de «es obligatorio» (auditoría 3.6, INV-09).
 */
export function idPayload(
    value: string | number | null | undefined,
): number | string | null {
    if (value === null || value === undefined) {
        return null;
    }

    const text = String(value).trim();

    if (text === '') {
        return null;
    }

    return /^\d+$/.test(text) && Number.isSafeInteger(Number(text))
        ? Number(text)
        : text;
}
