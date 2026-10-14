import { describe, expect, it } from 'vitest';

import {
    ingredientQuantityForInput,
    ingredientQuantityForServer,
} from '@/lib/production-ingredient-quantity';

/*
 * Auditoría 3.6, OPL-10: al completar la OP, `0,8` de consumo real se enviaba como 8. El consumo se escribe en la
 * unidad de la fórmula y se guarda en la de la materia prima, con el factor de conversión.
 */

describe('ingredientQuantityForServer', () => {
    it.each<[string, number | null, string]>([
        ['0,8', 1000, '800'],
        ['0.8', 1000, '800'],
        ['1,1', 1000, '1100'],
        ['800', 0.001, '0.8'],
        ['0,8', null, '0.8'],
        ['2,5', 0, '2.5'],
        ['', 1000, ''],
    ])('%j con factor %j → %j', (value, factor, expected) => {
        expect(ingredientQuantityForServer(value, factor)).toBe(expected);
    });

    it('un texto que no es un número se envía tal cual, para que lo rechace el servidor', () => {
        expect(ingredientQuantityForServer('abc', 1000)).toBe('abc');
    });
});

describe('ingredientQuantityForInput', () => {
    it.each<[string, number | null, string]>([
        ['800.0000', 1000, '0.8'],
        ['0.8000', 0.001, '800'],
        ['12.5000', null, '12.5'],
    ])('%j con factor %j → %j', (stored, factor, expected) => {
        expect(ingredientQuantityForInput(stored, factor)).toBe(expected);
    });
});
