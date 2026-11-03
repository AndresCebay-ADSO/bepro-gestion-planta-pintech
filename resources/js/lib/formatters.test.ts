import { describe, expect, it } from 'vitest';

import { formatSafeDate } from '@/components/formatted-date';
import {
    formatCurrency,
    formatForInput,
    formatKind,
    formatNumber,
    formatPercent,
    formatQuantity,
    isEmptyNumber,
    NUMBER_KINDS,
} from '@/lib/formatters';
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

describe('formatCurrency: un vacío nunca lleva símbolo de moneda', () => {
    it('sin valor devuelve solo el texto de vacío, «—» por defecto', () => {
        expect(formatCurrency(null)).toBe('—');
        expect(formatCurrency('', '$', { emptyValue: 'Sin precio' })).toBe(
            'Sin precio',
        );
        expect(
            formatCurrency(undefined, '$', { emptyValue: 'Sin costo' }),
        ).toBe('Sin costo');
    });

    it('con valor sigue llevando el símbolo', () => {
        expect(formatCurrency(1500)).toBe('$1.500');
    });
});

describe('formatPercent: porcentaje en puntos porcentuales', () => {
    it('formatea números como puntos porcentuales con coma decimal y sin ceros finales', () => {
        expect(formatPercent(19)).toBe('19%');
        expect(formatPercent('19')).toBe('19%');
        expect(formatPercent(19.5)).toBe('19,5%');
        expect(formatPercent('19.50')).toBe('19,5%');
        expect(formatPercent('19.555')).toBe('19,56%');
        expect(formatPercent(0)).toBe('0%');
        expect(formatPercent(-5.2)).toBe('-5,2%');
    });

    it('sin valor devuelve el texto de vacío, «—» por defecto', () => {
        expect(formatPercent(null)).toBe('—');
        expect(formatPercent(undefined)).toBe('—');
        expect(formatPercent('')).toBe('—');
        expect(formatPercent(null, 2, 'Sin dato')).toBe('Sin dato');
    });
});

describe('NUMBER_KINDS y formatKind: tipos aprobados para Pintech OS', () => {
    it('exporta las reglas de los 6 tipos aprobados', () => {
        expect(NUMBER_KINDS.quantity).toEqual({
            maxDecimals: 4,
            trimTrailingZeros: true,
        });
        expect(NUMBER_KINDS.units).toEqual({
            maxDecimals: 0,
            trimTrailingZeros: false,
        });
        expect(NUMBER_KINDS.clientPrice).toEqual({
            currency: '$',
            maxDecimals: 0,
            trimTrailingZeros: false,
        });
        expect(NUMBER_KINDS.cost).toEqual({
            currency: '$',
            maxDecimals: 2,
            trimTrailingZeros: true,
        });
        expect(NUMBER_KINDS.unitCost).toEqual({
            currency: '$',
            maxDecimals: 4,
            trimTrailingZeros: true,
        });
        expect(NUMBER_KINDS.percent).toEqual({
            maxDecimals: 2,
            trimTrailingZeros: true,
            isPercent: true,
        });
    });

    it('formatea cada tipo según la tabla de especificaciones', () => {
        expect(formatKind('12.5000', 'quantity')).toBe('12,5');
        expect(formatKind('0.0375', 'quantity')).toBe('0,0375');
        expect(formatKind(48, 'units')).toBe('48');
        expect(formatKind(1250, 'units')).toBe('1.250');
        expect(formatKind(1250000, 'clientPrice')).toBe('$1.250.000');
        expect(formatKind('12345.50', 'cost')).toBe('$12.345,5');
        expect(formatKind('12345.00', 'cost')).toBe('$12.345');
        expect(formatKind('4250.1234', 'unitCost')).toBe('$4.250,1234');
        expect(formatKind(19, 'percent')).toBe('19%');
        expect(formatKind(19.5, 'percent')).toBe('19,5%');
    });

    it('devuelve «—» para vacíos o el emptyValue solicitado', () => {
        expect(formatKind(null, 'quantity')).toBe('—');
        expect(formatKind('', 'clientPrice')).toBe('—');
        expect(formatKind(null, 'cost', 'Sin costo')).toBe('Sin costo');
    });
});

describe('formatNumber, formatQuantity y formatSafeDate: «—» por defecto', () => {
    it('devuelven «—» cuando falta el valor y respetan emptyValue cuando se pasa', () => {
        expect(formatNumber(null)).toBe('—');
        expect(formatNumber(undefined, { emptyValue: 'N/A' })).toBe('N/A');
        expect(formatQuantity('')).toBe('—');
        expect(formatSafeDate(null)).toBe('—');
        expect(formatSafeDate('', 'short', 'Sin estimar')).toBe('Sin estimar');
    });
});

describe('isEmptyNumber: mismo análisis de vacío que formatNumber', () => {
    it('marca como vacío null, undefined, cadena vacía y textos o números no finitos', () => {
        expect(isEmptyNumber(null)).toBe(true);
        expect(isEmptyNumber(undefined)).toBe(true);
        expect(isEmptyNumber('')).toBe(true);
        expect(isEmptyNumber('   ')).toBe(true);
        expect(isEmptyNumber('123abc')).toBe(true);
        expect(isEmptyNumber('1,5')).toBe(true);
        expect(isEmptyNumber(Number.NaN)).toBe(true);
        expect(isEmptyNumber(Number.POSITIVE_INFINITY)).toBe(true);
    });

    it('acepta el 0, negativos y decimales válidos en número o texto', () => {
        expect(isEmptyNumber(0)).toBe(false);
        expect(isEmptyNumber('0')).toBe(false);
        expect(isEmptyNumber('0.0000')).toBe(false);
        expect(isEmptyNumber(-12.5)).toBe(false);
        expect(isEmptyNumber(' 1500.25 ')).toBe(false);
    });

    it('no pone signo a un negativo que redondea a cero, como NumberFormat de PHP', () => {
        expect(formatKind('-0.00004', 'quantity')).toBe('0');
        expect(formatKind('-1250000', 'clientPrice')).toBe('-$1.250.000');
    });
});
