import { describe, expect, it } from 'vitest';

import { footerLayout } from '@/lib/detail-table';

/*
 * Filas de totales de `<DetailTable>` (DESIGN.md §9): la etiqueta llega hasta la primera cifra.
 */

const columns = ['product', 'type', 'quantity', 'price', 'subtotal'];

describe('fila de totales de una tabla de detalle', () => {
    it('extiende la etiqueta hasta la columna del valor', () => {
        expect(footerLayout(columns, ['subtotal'])).toEqual({
            labelSpan: 4,
            cellKeys: ['subtotal'],
        });
    });

    it('deja una celda por columna desde la primera con valor', () => {
        expect(footerLayout(columns, ['subtotal', 'quantity'])).toEqual({
            labelSpan: 2,
            cellKeys: ['quantity', 'price', 'subtotal'],
        });
    });

    it('sin valores, la etiqueta ocupa toda la fila', () => {
        expect(footerLayout(columns, [])).toEqual({
            labelSpan: 5,
            cellKeys: [],
        });
    });

    it('no deja que un valor en la primera columna tape la etiqueta', () => {
        expect(footerLayout(columns, ['product', 'subtotal'])).toEqual({
            labelSpan: 1,
            cellKeys: ['type', 'quantity', 'price', 'subtotal'],
        });
    });

    it('ignora claves que no son columnas', () => {
        expect(footerLayout(columns, ['total'])).toEqual({
            labelSpan: 5,
            cellKeys: [],
        });
    });
});
