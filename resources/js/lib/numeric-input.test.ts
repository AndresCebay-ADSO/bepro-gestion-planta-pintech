import { describe, expect, it } from 'vitest';

import {
    acceptsEdit,
    checkDecimalText,
    checkIntegerText,
    decimalAmbiguityHint,
    decimalLimitsMessage,
    integerLimitsMessage,
    isValidDecimalText,
    isValidIntegerText,
    parseDecimal,
    toDecimalPayload,
    withDecimalPayloads,
} from '@/lib/numeric-input';

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
            'Se registrará -1,2. Si es un número sin decimales, escríbelo sin punto: -1200.',
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
            'Se registrará 1,2. Si es un número sin decimales, escríbelo sin punto: 1200.',
        ],
        [
            '12.500',
            'Se registrará 12,5. Si es un número sin decimales, escríbelo sin punto: 12500.',
        ],
        [
            '1,250',
            'Se registrará 1,25. Si es un número sin decimales, escríbelo sin coma: 1250.',
        ],
        [
            '1.000',
            'Se registrará 1. Si es un número sin decimales, escríbelo sin punto: 1000.',
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
