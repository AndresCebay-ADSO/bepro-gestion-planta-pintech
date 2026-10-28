import { describe, expect, it } from 'vitest';

import {
    acceptsEdit,
    applyNumericEdit,
    checkDecimalText,
    checkIntegerText,
    decimalAmbiguityHint,
    decimalField,
    decimalLimitsMessage,
    formatDecimalDisplay,
    formatIntegerDisplay,
    idPayload,
    integerField,
    integerInWords,
    integerLimitsMessage,
    isValidDecimalText,
    isValidIntegerText,
    normalizeNumericInsertion,
    parseDecimal,
    replaceNumericRange,
    stripThousandsDots,
    toDecimalPayload,
    withDecimalPayloads,
} from '@/lib/numeric-input';
import type { NumericEdit, NumericField } from '@/lib/numeric-input';

/*
 * Auditoría 3.6, INV-15 (y OPC-11, OPL-10, OPX-02, PT-03): en los campos `type="number"`, `1,5` se guardaba como 15.
 * Regla del 2026-10-14: el punto y la coma son los dos separador decimal, nunca de miles, y se admite uno solo.
 */

describe('isValidDecimalText: un número válido para el campo', () => {
    it.each([
        ['', 'vacío'],
        ['1', 'entero'],
        ['1,5', 'coma decimal'],
        ['1.5', 'punto decimal'],
        ['1.200', 'punto con tres decimales (uno coma dos)'],
        ['1,', 'separador al final, mientras se escribe'],
        [',5', 'separador al inicio'],
        ['1200', 'mil doscientos, sin separador'],
        ['0,0001', 'cuatro decimales'],
        ['12345678,1234', 'el máximo de numeric(12,4)'],
    ])('acepta %j (%s)', (text) => {
        expect(isValidDecimalText(text)).toBe(true);
    });

    it.each([
        ['1.200,5', 'dos separadores (punto de miles y coma decimal)'],
        ['1,2,3', 'dos comas'],
        ['1..2', 'dos puntos'],
        ['1 200', 'espacio como separador de miles'],
        [' 1', 'espacio al inicio'],
        ['abc', 'letras'],
        ['1e3', 'notación científica'],
        ['-1', 'negativo'],
        ['+1', 'signo más'],
        ['1,12345', 'cinco decimales'],
        ['123456789', 'nueve dígitos enteros'],
        ['１', 'dígito de ancho completo'],
    ])('rechaza %j (%s)', (text) => {
        expect(isValidDecimalText(text)).toBe(false);
    });

    it('respeta los decimales y enteros de cada columna', () => {
        expect(isValidDecimalText('52,4', { maxDecimals: 2 })).toBe(true);
        expect(isValidDecimalText('52,45', { maxDecimals: 2 })).toBe(true);
        expect(isValidDecimalText('52,455', { maxDecimals: 2 })).toBe(false);
        expect(isValidDecimalText('100', { maxIntegerDigits: 3 })).toBe(true);
        expect(isValidDecimalText('1000', { maxIntegerDigits: 3 })).toBe(false);
        expect(isValidDecimalText('1,', { maxDecimals: 0 })).toBe(false);
    });
});

describe('isValidIntegerText: unidades, envases y etiquetas', () => {
    it.each(['', '0', '12', '99999999'])('acepta %j', (text) => {
        expect(isValidIntegerText(text)).toBe(true);
    });

    it.each(['1,5', '1.5', '1.000', '1 000', '-1', '1e3', 'a', '123456789'])(
        'rechaza %j',
        (text) => {
            expect(isValidIntegerText(text)).toBe(false);
        },
    );
});

/**
 * Escribe el texto tecla por tecla, como el componente: cada tecla se acepta o se rechaza según `check`.
 */
function typeKeys(
    keys: string,
    check: (text: string) => 'valid' | 'separator-error' | 'reject',
): string {
    let text = '';

    for (const key of keys) {
        if (check(text + key) !== 'reject') {
            text += key;
        }
    }

    return text;
}

describe('checkDecimalText: lo que hace el campo con cada tecla o texto pegado', () => {
    it.each<[string, string]>([
        ['1,5e,3 x', '1,5,3'],
        ['1.200,5', '1.200,5'],
        ['1,23456', '1,2345'],
        ['123456789', '12345678'],
        ['1 200', '1200'],
    ])(
        'al teclear %j queda %j: nunca se quita un separador, porque los dígitos siguientes cambiarían de lugar',
        (keys, expected) => {
            expect(typeKeys(keys, (text) => checkDecimalText(text))).toBe(
                expected,
            );
        },
    );

    it.each(['1.200,5', '1,2,3', '1..2'])(
        'un separador de más se deja escrito y marca el error: %j',
        (text) => {
            expect(checkDecimalText(text)).toBe('separator-error');
        },
    );

    it.each(['1 200', 'abc', '1e3', '-1', '1,23456', '123456789', '1.200,5x'])(
        'lo demás se rechaza completo, también al pegar: %j',
        (text) => {
            expect(checkDecimalText(text)).toBe('reject');
        },
    );

    it.each(['', '1,5', '1.200'])('acepta %j sin error', (text) => {
        expect(checkDecimalText(text)).toBe('valid');
    });
});

describe('checkIntegerText', () => {
    it('al teclear 3,5 queda 3,5 con error, no 35', () => {
        expect(typeKeys('3,5', (text) => checkIntegerText(text))).toBe('3,5');
        expect(checkIntegerText('3,5')).toBe('separator-error');
        expect(checkIntegerText('1.000')).toBe('separator-error');
    });

    it.each(['a', '-1', '1 0', '1e3', '123456789'])('rechaza %j', (text) => {
        expect(checkIntegerText(text)).toBe('reject');
    });

    it.each(['', '0', '15'])('acepta %j sin error', (text) => {
        expect(checkIntegerText(text)).toBe('valid');
    });
});

describe('toDecimalPayload: lo que se envía al servidor', () => {
    it.each<[string | number | null | undefined, string]>([
        ['1,5', '1.5'],
        ['1.5', '1.5'],
        ['1.200', '1.200'],
        ['1200', '1200'],
        ['1,', '1'],
        [',5', '0.5'],
        [' 1,5 ', '1.5'],
        ['12.5000', '12.5000'],
        [0, '0'],
        [2.5, '2.5'],
    ])('%j → %j', (value, expected) => {
        expect(toDecimalPayload(value)).toBe(expected);
    });

    it.each<[string | null | undefined]>([
        [''],
        [null],
        [undefined],
        [','],
        ['.'],
    ])('el vacío sigue vacío, nunca 0: %j', (value) => {
        expect(toDecimalPayload(value)).toBe('');
    });

    it.each(['abc', '1.200,5', '1-'])(
        'un texto que no es un número se envía tal cual, para que lo rechace el servidor: %j',
        (value) => {
            expect(toDecimalPayload(value)).toBe(value);
        },
    );
});

describe('negativos: solo en los campos con allowNegative (ajuste de precio, temperaturas)', () => {
    const signed = { allowNegative: true, maxDecimals: 2, maxIntegerDigits: 3 };

    it.each(['', '-', '-1', '-10,5', '-,5', '-100', '5', '99,99'])(
        'acepta %j',
        (text) => {
            expect(checkDecimalText(text, signed)).toBe('valid');
        },
    );

    it.each(['1-', '--1', '-1-', '+5', '- 1', '-1000', '-1,234', '-a'])(
        'rechaza %j',
        (text) => {
            expect(checkDecimalText(text, signed)).toBe('reject');
        },
    );

    it('un separador de más también marca el error con signo', () => {
        expect(checkDecimalText('-1.200,5', signed)).toBe('separator-error');
    });

    it('al teclear -10,5 queda -10,5; el signo en medio se rechaza', () => {
        expect(
            typeKeys('-10,5', (text) => checkDecimalText(text, signed)),
        ).toBe('-10,5');
        expect(typeKeys('10-5', (text) => checkDecimalText(text, signed))).toBe(
            '105',
        );
    });

    it('sin allowNegative el signo se rechaza', () => {
        expect(checkDecimalText('-', { maxDecimals: 2 })).toBe('reject');
        expect(checkDecimalText('-1', { maxDecimals: 2 })).toBe('reject');
    });

    it.each<[string, string]>([
        ['-10,5', '-10.5'],
        ['-10.5', '-10.5'],
        ['-,5', '-0.5'],
        ['-1,', '-1'],
        ['-15', '-15'],
    ])('se envía %j como %j', (value, expected) => {
        expect(toDecimalPayload(value)).toBe(expected);
    });

    it.each(['-', '-,', '-.'])(
        'un signo solo se envía vacío, nunca 0: %j',
        (value) => {
            expect(toDecimalPayload(value)).toBe('');
        },
    );

    it.each<[string, number | null]>([
        ['-10,5', -10.5],
        ['-,5', -0.5],
        ['-', null],
    ])('parseDecimal(%j) → %j', (value, expected) => {
        expect(parseDecimal(value)).toBe(expected);
    });

    it('el aviso de miles conserva el signo', () => {
        expect(decimalAmbiguityHint('-1.200')).toBe(
            'Se registrará -1,2. Si es menos mil doscientos, escríbelo sin punto ni coma: -1200.',
        );
    });
});

describe('acceptsEdit: un valor que ya no cabe en el campo se puede borrar', () => {
    // Margen de costos: 2 decimales, 3 enteros, sin negativos. El precio por debajo del costo lo deja en -1900.
    const margin = (text: string) =>
        checkDecimalText(text, { maxDecimals: 2, maxIntegerDigits: 3 });

    it('borrar desde -1900 avanza tecla por tecla hasta quedar vacío', () => {
        let text = '-1900';

        for (const next of ['-190', '-19', '-1', '-', '']) {
            expect(acceptsEdit(next, text, margin)).toBe(true);
            text = next;
        }

        expect(margin(text)).toBe('valid');
    });

    it('con el valor fuera de límites, lo que lo alarga sigue rechazado', () => {
        expect(acceptsEdit('-19005', '-1900', margin)).toBe(false);
        expect(acceptsEdit('-1900x', '-1900', margin)).toBe(false);
    });

    it('con el valor fuera de límites, las letras se rechazan aunque acorten el texto', () => {
        expect(acceptsEdit('a', '-5.26', margin)).toBe(false);
        expect(acceptsEdit('-5a', '-5.26', margin)).toBe(false);
        expect(acceptsEdit(' 5', '-5.26', margin)).toBe(false);
        expect(acceptsEdit('5-', '-5.26', margin)).toBe(false);
        expect(acceptsEdit('-5.2', '-5.26', margin)).toBe(true);
    });

    it('con un valor válido, borrar no habilita lo inválido', () => {
        expect(acceptsEdit('12', '123', margin)).toBe(true);
        expect(acceptsEdit('1,234', '1,23', margin)).toBe(false);
    });

    it('cada componente explica por qué el valor no cabe', () => {
        expect(
            decimalLimitsMessage({ maxDecimals: 2, maxIntegerDigits: 3 }),
        ).toBe('No cabe: hasta 3 enteros y 2 decimales, sin negativos.');
        expect(
            decimalLimitsMessage({
                maxDecimals: 1,
                maxIntegerDigits: 3,
                allowNegative: true,
            }),
        ).toBe('No cabe: hasta 3 enteros y 1 decimal.');
        expect(integerLimitsMessage(4)).toBe(
            'No cabe: solo enteros, hasta 4 dígitos.',
        );
    });
});

describe('withDecimalPayloads', () => {
    it('convierte solo los campos indicados', () => {
        expect(
            withDecimalPayloads(
                { name: 'A,B', cif: '15,5', threshold: '', active: true },
                ['cif', 'threshold'],
            ),
        ).toEqual({ name: 'A,B', cif: '15.5', threshold: '', active: true });
    });
});

describe('parseDecimal: el número para los cálculos en pantalla', () => {
    it.each<[string | number | null, number | null]>([
        ['1,5', 1.5],
        ['1.5', 1.5],
        ['1.200', 1.2],
        ['1200', 1200],
        [',5', 0.5],
        [0, 0],
        ['', null],
        [',', null],
        ['abc', null],
        [null, null],
    ])('%j → %j', (value, expected) => {
        expect(parseDecimal(value)).toBe(expected);
    });
});

describe('decimalAmbiguityHint: aviso cuando se escribe como miles', () => {
    it.each([
        [
            '1.200',
            'Se registrará 1,2. Si es mil doscientos, escríbelo sin punto ni coma: 1200.',
        ],
        [
            '12.500',
            'Se registrará 12,5. Si es doce mil quinientos, escríbelo sin punto ni coma: 12500.',
        ],
        [
            '1,250',
            'Se registrará 1,25. Si es mil doscientos cincuenta, escríbelo sin punto ni coma: 1250.',
        ],
        [
            '1.000',
            'Se registrará 1. Si es mil, escríbelo sin punto ni coma: 1000.',
        ],
    ])('avisa en %j', (text, hint) => {
        expect(decimalAmbiguityHint(text)).toBe(hint);
    });

    it.each(['', '1200', '1.5', '1,25', '1.2000', '0.500', '0,125', '1,'])(
        'no avisa en %j',
        (text) => {
            expect(decimalAmbiguityHint(text)).toBeNull();
        },
    );
});

/*
 * Auditoría 3.6, INV-09: `Number('')` es 0, y el servidor respondía «seleccionado no es válido» en vez de «es
 * obligatorio». Un ID sin elegir se envía vacío (null) para que el servidor diga qué falta.
 */
describe('idPayload: el ID que se envía al servidor', () => {
    it.each([
        ['12', 12],
        [12, 12],
        [' 7 ', 7],
        ['0', 0],
    ])('un ID elegido (%j) viaja como número', (value, expected) => {
        expect(idPayload(value)).toBe(expected);
    });

    it.each(['', '   ', null, undefined])(
        'un ID sin elegir (%j) viaja como null, nunca como 0',
        (value) => {
            expect(idPayload(value)).toBeNull();
        },
    );

    it.each(['abc', '1.5', '-3', '9223372036854775808'])(
        'un valor que no es un entero seguro (%j) viaja tal cual, para que el servidor lo rechace',
        (value) => {
            expect(idPayload(value)).toBe(value);
        },
    );
});

describe('integerInWords: el número del aviso, en palabras', () => {
    it.each([
        ['0', 'cero'],
        ['1', 'uno'],
        ['16', 'dieciséis'],
        ['21', 'veintiuno'],
        ['31', 'treinta y uno'],
        ['100', 'cien'],
        ['101', 'ciento uno'],
        ['1000', 'mil'],
        ['1001', 'mil uno'],
        ['1250', 'mil doscientos cincuenta'],
        ['12500', 'doce mil quinientos'],
        ['21000', 'veintiún mil'],
        ['31000', 'treinta y un mil'],
        ['101000', 'ciento un mil'],
        ['100000', 'cien mil'],
        ['1000000', 'un millón'],
        ['2500000', 'dos millones quinientos mil'],
        ['21000000', 'veintiún millones'],
        ['1000000000', 'mil millones'],
        [
            '12345678123',
            'doce mil trescientos cuarenta y cinco millones seiscientos setenta y ocho mil ciento veintitrés',
        ],
        ['1000000000000', 'un billón'],
        ['01250', 'mil doscientos cincuenta'],
    ])('%s → «%s»', (digits, words) => {
        expect(integerInWords(digits)).toBe(words);
    });
});

/*
 * Miles mientras se escribe: el formulario guarda el texto sin miles (`1200,5`) y el campo muestra `1.200,5`. El punto
 * que se teclea es decimal.
 */

describe('formatDecimalDisplay: lo que se ve en un campo decimal', () => {
    it.each([
        ['', ''],
        ['5', '5'],
        ['1200', '1.200'],
        ['1200.5', '1.200,5'],
        ['1200,5', '1.200,5'],
        ['0,5', '0,5'],
        [',5', ',5'],
        ['1200,', '1.200,'],
        ['12345678,1234', '12.345.678,1234'],
        ['1,250', '1,250'],
    ])('%j → %j', (text, display) => {
        expect(formatDecimalDisplay(text)).toBe(display);
    });

    it('agrupa los negativos en los campos que los admiten', () => {
        expect(formatDecimalDisplay('-1200,5', { allowNegative: true })).toBe(
            '-1.200,5',
        );
        expect(formatDecimalDisplay('-', { allowNegative: true })).toBe('-');
    });

    it.each([
        ['1,200,5', 'texto con dos separadores'],
        ['1.200,5', 'punto y coma'],
        ['123456789', 'más enteros de los que caben (llegó del servidor)'],
        ['1,12345', 'más decimales de los que caben'],
        ['-5.26', 'negativo en un campo sin negativos'],
    ])('deja tal cual %j (%s)', (text) => {
        expect(formatDecimalDisplay(text)).toBe(text);
    });
});

describe('formatIntegerDisplay: lo que se ve en un campo de enteros', () => {
    it.each([
        ['', ''],
        ['12', '12'],
        ['1200', '1.200'],
        ['1250000', '1.250.000'],
    ])('%j → %j', (text, display) => {
        expect(formatIntegerDisplay(text)).toBe(display);
    });

    it.each(['3,5', '1200.', '123456789'])('deja tal cual %j', (text) => {
        expect(formatIntegerDisplay(text)).toBe(text);
    });
});

describe('stripThousandsDots: quitar el formato', () => {
    it.each([
        ['1.200,5', '1200,5'],
        ['-12.345.678,1234', '-12345678,1234'],
        ['1.200', '1200'],
        [',5', ',5'],
    ])('%j → %j', (display, text) => {
        expect(stripThousandsDots(display)).toBe(text);
    });
});

describe('normalizeNumericInsertion: lo pegado, sin formato', () => {
    it.each([
        ['1.250.000,75', '1250000,75', 'punto y coma: el último es el decimal'],
        ['1,250,000.75', '1250000.75', 'coma y punto: el último es el decimal'],
        ['1.250.000', '1250000', 'el mismo separador repetido son miles'],
        ['1,250,000', '1250000', 'la misma coma repetida son miles'],
        ['1250000.75', '1250000.75', 'un solo separador es decimal'],
        ['1.250', '1.250', 'un solo punto es decimal, igual que al teclear'],
        ['1 250 000,75', '1250000,75', 'espacios'],
        ['1\u00a0250\u202f000', '1250000', 'espacios duros'],
        ['$ 1.250.000', '1250000', 'signo de pesos'],
        ['-1.250,5', '-1250,5', 'negativo'],
        ['.', '.', 'el punto tecleado'],
        ['', '', 'vacío'],
    ])('%j → %j (%s)', (pasted, text) => {
        expect(normalizeNumericInsertion(pasted)).toBe(text);
    });

    it('deja las letras para que el campo las rechace', () => {
        expect(normalizeNumericInsertion('12 kg')).toBe('12kg');
    });
});

/** Lo que se ve después del cambio, con el cursor marcado con `|`. */
function shown(edit: NumericEdit): string {
    return `${edit.display.slice(0, edit.caret)}|${edit.display.slice(edit.caret)}`;
}

/** Teclear `typed` con el cursor en `caret` de lo que se ve. */
function typeAt(
    field: NumericField,
    current: string,
    caret: number,
    typed: string,
): NumericEdit {
    const display = field.format(current);

    return applyNumericEdit(
        field,
        current,
        `${display.slice(0, caret)}${typed}${display.slice(caret)}`,
        caret + typed.length,
        'insertText',
    );
}

/** La tecla de borrar con el cursor en `caret`. */
function backspaceAt(
    field: NumericField,
    current: string,
    caret: number,
): NumericEdit {
    const display = field.format(current);

    return applyNumericEdit(
        field,
        current,
        `${display.slice(0, caret - 1)}${display.slice(caret)}`,
        caret - 1,
        'deleteContentBackward',
    );
}

/** Supr con el cursor en `caret`. */
function deleteAt(
    field: NumericField,
    current: string,
    caret: number,
): NumericEdit {
    const display = field.format(current);

    return applyNumericEdit(
        field,
        current,
        `${display.slice(0, caret)}${display.slice(caret + 1)}`,
        caret,
        'deleteContentForward',
    );
}

/** Teclear todo el texto, tecla por tecla, desde el campo vacío. */
function typeAll(field: NumericField, keys: string): NumericEdit {
    let edit: NumericEdit = { value: '', display: '', caret: 0 };

    for (const key of keys) {
        edit = typeAt(field, edit.value, edit.caret, key);
    }

    return edit;
}

describe('applyNumericEdit: teclear en un campo decimal', () => {
    const decimal = decimalField();

    it.each([
        ['1200,5', '1200,5', '1.200,5|'],
        ['1200.5', '1200,5', '1.200,5|'],
        ['12345678,1234', '12345678,1234', '12.345.678,1234|'],
        ['.5', ',5', ',5|'],
        ['1250', '1250', '1.250|'],
        ['1.250', '1,250', '1,250|'],
    ])('%j se guarda %j y se ve %j', (keys, value, display) => {
        const edit = typeAll(decimal, keys);

        expect(edit.value).toBe(value);
        expect(shown(edit)).toBe(display);
    });

    it('el punto de miles tecleado por costumbre queda escrito, sin agrupar y con error', () => {
        const edit = typeAll(decimal, '1.200,5');

        expect(edit.value).toBe('1,200,5');
        expect(edit.display).toBe('1,200,5');
        expect(checkDecimalText(edit.value)).toBe('separator-error');
    });

    it('rechaza letras, espacios y lo que no cabe, con el cursor donde estaba', () => {
        expect(shown(typeAt(decimal, '1200', 1, 'a'))).toBe('1|.200');
        expect(shown(typeAt(decimal, '1200', 5, ' '))).toBe('1.200|');
        expect(typeAt(decimal, '12345678', 10, '9').value).toBe('12345678');
        expect(typeAt(decimal, '1,1234', 6, '5').value).toBe('1,1234');
        expect(typeAt(decimal, '5', 0, '-').value).toBe('5');
    });

    it('el cursor queda tras los mismos dígitos cuando se mueven los puntos', () => {
        expect(shown(typeAt(decimal, '100', 3, '0'))).toBe('1.000|');
        expect(shown(typeAt(decimal, '1000', 0, '5'))).toBe('5|1.000');
        expect(shown(typeAt(decimal, '1200', 1, '5'))).toBe('15|.200');
        expect(shown(typeAt(decimal, '1200', 2, '5'))).toBe('15|.200');
        expect(shown(typeAt(decimal, '1200', 3, '5'))).toBe('12.5|00');
        expect(shown(typeAt(decimal, '1200', 5, '.'))).toBe('1.200,|');
    });

    it('un punto tecleado en medio es decimal', () => {
        const edit = typeAt(decimal, '1200', 1, '.');

        expect(edit.value).toBe('1,200');
        expect(shown(edit)).toBe('1,|200');
    });

    it('agrupa los negativos', () => {
        const negative = decimalField({ allowNegative: true });

        expect(shown(typeAll(negative, '-1200.5'))).toBe('-1.200,5|');
    });
});

describe('applyNumericEdit: borrar y suprimir en un campo decimal', () => {
    const decimal = decimalField();

    it('borrar un dígito reagrupa y deja el cursor tras los mismos dígitos', () => {
        expect(shown(backspaceAt(decimal, '1200', 3))).toBe('1|00');
        expect(shown(backspaceAt(decimal, '1200', 5))).toBe('120|');
        expect(shown(backspaceAt(decimal, '1250000', 1))).toBe('|250.000');
    });

    it('borrar justo después de un punto de miles salta el punto y borra el dígito anterior', () => {
        const edit = backspaceAt(decimal, '1200', 2);

        expect(edit.value).toBe('200');
        expect(shown(edit)).toBe('|200');
        expect(shown(backspaceAt(decimal, '1250000', 6))).toBe('125|.000');
    });

    it('Supr justo antes de un punto de miles salta el punto y borra el dígito siguiente', () => {
        const edit = deleteAt(decimal, '1200', 1);

        expect(edit.value).toBe('100');
        expect(shown(edit)).toBe('1|00');
        expect(shown(deleteAt(decimal, '1250000', 1))).toBe('1|50.000');
    });

    it('Supr en un dígito reagrupa', () => {
        expect(shown(deleteAt(decimal, '1200', 0))).toBe('|200');
        expect(shown(deleteAt(decimal, '1200,5', 4))).toBe('120|,5');
        expect(shown(deleteAt(decimal, '1200,5', 5))).toBe('12.00|5');
    });

    it('borrar la coma junta el número y lo agrupa', () => {
        expect(shown(backspaceAt(decimal, '12,345', 3))).toBe('12|.345');
    });

    it('un valor del servidor que no cabe se ve tal cual y se puede borrar', () => {
        const margin = decimalField({ maxDecimals: 2 });
        const edit = backspaceAt(margin, '-5.26', 5);

        expect(margin.format('-5.26')).toBe('-5.26');
        expect(edit.value).toBe('-5.2');
        expect(shown(edit)).toBe('-5.2|');
        expect(typeAt(margin, '-5.26', 5, '1').value).toBe('-5.26');
    });

    it('con error, se borra como texto normal hasta que vuelve a agruparse', () => {
        const edit = backspaceAt(decimal, '1,200,5', 7);

        expect(edit.value).toBe('1,200,');
        expect(edit.display).toBe('1,200,');

        const fixed = backspaceAt(decimal, '1200,,5', 6);

        expect(fixed.value).toBe('1200,5');
        expect(shown(fixed)).toBe('1.200,|5');
    });

    it('reemplazar una selección que contiene un punto de miles', () => {
        // «2.3» de «12.345» seleccionado y reemplazado por «9».
        const edit = replaceNumericRange(
            decimal,
            '12345',
            1,
            4,
            '9',
            'insertText',
        );

        expect(edit.value).toBe('1945');
        expect(shown(edit)).toBe('1.9|45');
    });

    it('reemplazar «2.» de «12.345» por «2» no borra el dígito de al lado', () => {
        // Lo que se ve queda «12345»: comparando, solo falta el punto, pero no fue la tecla de borrar.
        const edit = applyNumericEdit(
            decimal,
            '12345',
            '12345',
            2,
            'insertText',
        );

        expect(edit.value).toBe('12345');
        expect(shown(edit)).toBe('12|.345');
    });
});

describe('replaceNumericRange: pegar', () => {
    const decimal = decimalField();
    const money = decimalField({ maxDecimals: 2, maxIntegerDigits: 12 });

    it.each([
        ['1.250.000,75', '1250000,75', '1.250.000,75|'],
        ['1250000.75', '1250000,75', '1.250.000,75|'],
        ['1 250 000,75', '1250000,75', '1.250.000,75|'],
        ['$ 1.250.000', '1250000', '1.250.000|'],
        ['1.250', '1,250', '1,250|'],
    ])('%j en el campo vacío', (pasted, value, display) => {
        const edit = replaceNumericRange(decimal, '', 0, 0, pasted);

        expect(edit.value).toBe(value);
        expect(shown(edit)).toBe(display);
    });

    it('pegado sobre todo el texto lo reemplaza, aunque empiece igual', () => {
        const edit = replaceNumericRange(money, '1250', 0, 5, '1.250.000');

        expect(edit.value).toBe('1250000');
        expect(shown(edit)).toBe('1.250.000|');
    });

    it('pegado en medio, el cursor queda al final de lo pegado', () => {
        expect(shown(replaceNumericRange(decimal, '15', 1, 1, '000'))).toBe(
            '10.00|5',
        );
    });

    it('rechaza completo lo que tiene letras o no cabe', () => {
        expect(replaceNumericRange(decimal, '12', 2, 2, '5 kg').value).toBe(
            '12',
        );
        expect(replaceNumericRange(decimal, '12', 0, 2, 'abc').value).toBe(
            '12',
        );
        expect(
            replaceNumericRange(decimal, '', 0, 0, '1.250.000.000').value,
        ).toBe('');
    });
});

describe('applyNumericEdit: campo de enteros', () => {
    const integer = integerField(10);

    it.each([
        ['1200', '1200', '1.200|'],
        ['1250000', '1250000', '1.250.000|'],
    ])('%j se guarda %j y se ve %j', (keys, value, display) => {
        const edit = typeAll(integer, keys);

        expect(edit.value).toBe(value);
        expect(shown(edit)).toBe(display);
    });

    it('un punto o una coma tecleados quedan escritos, sin agrupar y con error', () => {
        const dot = typeAll(integer, '1200.5');
        const comma = typeAll(integer, '3,5');

        expect(dot.value).toBe('1200.5');
        expect(dot.display).toBe('1200.5');
        expect(checkIntegerText(dot.value)).toBe('separator-error');
        expect(comma.value).toBe('3,5');
    });

    it('borrar y suprimir junto a un punto de miles', () => {
        expect(shown(backspaceAt(integer, '1200', 2))).toBe('|200');
        expect(shown(deleteAt(integer, '1200', 1))).toBe('1|00');
    });

    it('pegar miles los quita', () => {
        expect(replaceNumericRange(integer, '', 0, 0, '1.250.000').value).toBe(
            '1250000',
        );
        expect(replaceNumericRange(integer, '', 0, 0, '1.250').value).toBe(
            '1.250',
        );
    });
});
