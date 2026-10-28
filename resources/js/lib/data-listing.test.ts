import { describe, expect, it } from 'vitest';

import {
    cardPairs,
    isEmptyCell,
    listingColumnProblems,
    visibleRowActions,
} from '@/lib/data-listing';
import type { MobileRole } from '@/lib/data-listing';

/*
 * Reglas de la definición de columnas de `<DataListing>` y del menú «⋯» de `<RowActions>` (DESIGN.md §9).
 */

const column = (key: string, mobile: MobileRole) => ({ key, mobile });

describe('columnas de un listado', () => {
    it('acepta un título, un subtítulo, un estado, 4 datos y columnas ocultas', () => {
        expect(
            listingColumnProblems([
                column('code', 'title'),
                column('category', 'subtitle'),
                column('status', 'status'),
                column('unit', 'meta'),
                column('price', 'meta'),
                column('stock', 'meta'),
                column('date', 'meta'),
                column('alerts', 'hidden'),
                column('notes', 'hidden'),
            ]),
        ).toEqual([]);
    });

    it('acepta solo el título', () => {
        expect(listingColumnProblems([column('code', 'title')])).toEqual([]);
    });

    it('exige exactamente un título', () => {
        expect(listingColumnProblems([column('code', 'meta')])).toHaveLength(1);
        expect(
            listingColumnProblems([
                column('code', 'title'),
                column('name', 'title'),
            ]),
        ).toHaveLength(1);
    });

    it('no admite dos subtítulos ni dos estados', () => {
        expect(
            listingColumnProblems([
                column('code', 'title'),
                column('client', 'subtitle'),
                column('product', 'subtitle'),
                column('status', 'status'),
                column('priority', 'status'),
            ]),
        ).toHaveLength(2);
    });

    it('acepta un dato principal además de los 4 datos', () => {
        expect(
            listingColumnProblems([
                column('code', 'title'),
                column('stock', 'primary'),
                ...['a', 'b', 'c', 'd'].map((key) => column(key, 'meta')),
            ]),
        ).toEqual([]);
    });

    it('no admite dos datos principales', () => {
        expect(
            listingColumnProblems([
                column('code', 'title'),
                column('stock', 'primary'),
                column('price', 'primary'),
            ]),
        ).toEqual(['Solo puede haber una columna «primary» (hay 2).']);
    });

    it('no admite más de 4 datos en la tarjeta', () => {
        expect(
            listingColumnProblems([
                column('code', 'title'),
                ...['a', 'b', 'c', 'd', 'e'].map((key) => column(key, 'meta')),
            ]),
        ).toHaveLength(1);
    });

    it('no admite claves repetidas', () => {
        expect(
            listingColumnProblems([
                column('code', 'title'),
                column('stock', 'meta'),
                column('stock', 'hidden'),
            ]),
        ).toEqual(['Claves de columna repetidas: stock.']);
    });
});

describe('celda sin dato', () => {
    it('es null, undefined, false o el texto vacío', () => {
        expect(isEmptyCell(null)).toBe(true);
        expect(isEmptyCell(undefined)).toBe(true);
        expect(isEmptyCell(false)).toBe(true);
        expect(isEmptyCell('')).toBe(true);
    });

    it('el 0 y un texto con contenido sí son datos', () => {
        expect(isEmptyCell(0)).toBe(false);
        expect(isEmptyCell(' ')).toBe(false);
        expect(isEmptyCell('—')).toBe(false);
    });
});

describe('pares de la tarjeta del celular', () => {
    type Row = Record<string, string | number | null>;

    const cellColumn = (key: string, mobile: MobileRole) => ({
        key,
        mobile,
        cell: (row: Row) => row[key] ?? null,
    });
    const keysOf = (pairs: { column: { key: string } }[]) =>
        pairs.map(({ column }) => column.key);

    it('pone el principal primero y luego los datos en su orden', () => {
        const columns = [
            cellColumn('code', 'title'),
            cellColumn('date', 'meta'),
            cellColumn('client', 'subtitle'),
            cellColumn('stock', 'primary'),
            cellColumn('status', 'status'),
            cellColumn('price', 'meta'),
            cellColumn('notes', 'hidden'),
        ];
        const row = {
            code: 'MP-001',
            date: '2026-10-28',
            client: 'Cliente',
            stock: 120,
            status: 'Activo',
            price: 5000,
            notes: 'Nota',
        };

        expect(cardPairs(columns, row)).toEqual([
            { column: columns[3], value: 120 },
            { column: columns[1], value: '2026-10-28' },
            { column: columns[5], value: 5000 },
        ]);
    });

    it('omite los que no tienen dato, pero no el 0', () => {
        const columns = [
            cellColumn('code', 'title'),
            cellColumn('stock', 'primary'),
            cellColumn('date', 'meta'),
            cellColumn('seller', 'meta'),
            cellColumn('alerts', 'meta'),
        ];

        expect(
            keysOf(
                cardPairs(columns, {
                    code: 'MP-001',
                    stock: 0,
                    date: null,
                    seller: '',
                }),
            ),
        ).toEqual(['stock']);
    });

    it('queda vacío sin principal ni datos', () => {
        expect(
            cardPairs(
                [cellColumn('code', 'title'), cellColumn('notes', 'hidden')],
                { code: 'MP-001', notes: 'Nota' },
            ),
        ).toEqual([]);
    });
});

describe('acciones del menú «⋯»', () => {
    it('quita las ocultas y deja las destructivas al final, sin cambiar el orden', () => {
        const actions = [
            { label: 'Eliminar', destructive: true },
            { label: 'Editar' },
            { label: 'Desactivar', hidden: true },
            { label: 'Reactivar' },
        ];

        const { regular, destructive } = visibleRowActions(actions);

        expect(regular.map((action) => action.label)).toEqual([
            'Editar',
            'Reactivar',
        ]);
        expect(destructive.map((action) => action.label)).toEqual(['Eliminar']);
    });

    it('queda vacío si todas están ocultas', () => {
        const { regular, destructive } = visibleRowActions([
            { label: 'Editar', hidden: true },
            { label: 'Eliminar', destructive: true, hidden: true },
        ]);

        expect([...regular, ...destructive]).toEqual([]);
    });
});
