import { describe, expect, it } from 'vitest';

import { formatForInput } from '@/lib/formatters';
import { NUMBER_TYPES } from '@/lib/number-types';
import { checkDecimalText } from '@/lib/numeric-input';

/*
 * Revisión del PR 5 de la auditoría 3.6: el costo de un lote llega de la base como `"1500.0000"` (decimal(16,4)) y el
 * campo de dinero admite 2 decimales. Copiado tal cual, el campo quedaba en error y el servidor rechazaba la entrada.
 */

describe('formatForInput: un valor guardado listo para un campo', () => {
    it.each([
        ['1500.0000', '1500'],
        ['1500.5000', '1500.5'],
        ['1500.5050', '1500.505'],
        ['0.0000', '0'],
        ['0.2500', '0.25'],
        ['10', '10'],
        ['007.10', '7.1'],
        ['.5', '0.5'],
        ['+3', '3'],
        ['-2.5000', '-2.5'],
        ['-0.0000', '0'],
        [' 12.30 ', '12.3'],
    ])('%s → %s', (value, expected) => {
        expect(formatForInput(value)).toBe(expected);
    });

    it('no pierde dígitos en el dinero más grande: no pasa por un float', () => {
        expect(formatForInput('999999999999.9999')).toBe('999999999999.9999');
        expect(formatForInput('123456789012.3400')).toBe('123456789012.34');
    });

    it('acepta números y deja vacío lo que no es un número', () => {
        expect(formatForInput(5)).toBe('5');
        expect(formatForInput(2.5)).toBe('2.5');
        expect(formatForInput(Number.NaN)).toBe('');
        expect(formatForInput(null)).toBe('');
        expect(formatForInput(undefined)).toBe('');
        expect(formatForInput('')).toBe('');
        expect(formatForInput('abc')).toBe('');
        expect(formatForInput('.')).toBe('');
        expect(formatForInput('1,5')).toBe('');
    });

    it('el costo de un lote cabe en el campo de dinero', () => {
        expect(checkDecimalText('1500.0000', NUMBER_TYPES.money)).toBe(
            'reject',
        );
        expect(
            checkDecimalText(formatForInput('1500.0000'), NUMBER_TYPES.money),
        ).toBe('valid');
        expect(
            checkDecimalText(formatForInput('1500.5000'), NUMBER_TYPES.money),
        ).toBe('valid');
    });
});
