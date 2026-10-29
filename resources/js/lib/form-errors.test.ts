import { describe, expect, it } from 'vitest';

import {
    clearListErrors,
    formErrorMessages,
    listErrorKeys,
} from '@/lib/form-errors';

/*
 * Auditoría 3.6, OPL-11: al completar la OP, la falta de stock de un ajuste (`line_adjustments`) o un error de fila
 * (`packaging.0.id`) llegaban con una clave que ningún campo mostraba.
 */

describe('formErrorMessages', () => {
    it('incluye las claves sin campo y las de fila, en el orden del servidor', () => {
        expect(
            formErrorMessages({
                line_adjustments: 'Stock insuficiente para el ajuste de línea.',
                'packaging.0.id': 'La orden cambió.',
                density_kg_per_gallon: 'La densidad es obligatoria.',
            }),
        ).toEqual([
            'Stock insuficiente para el ajuste de línea.',
            'La orden cambió.',
            'La densidad es obligatoria.',
        ]);
    });

    it('no repite un mensaje que llega en dos claves', () => {
        expect(
            formErrorMessages({
                packaging: 'La orden cambió.',
                line_adjustment_ids: 'La orden cambió.',
            }),
        ).toEqual(['La orden cambió.']);
    });

    it('ignora las claves sin mensaje', () => {
        expect(formErrorMessages({ notes: undefined, packaging: ' ' })).toEqual(
            [],
        );
        expect(formErrorMessages({})).toEqual([]);
    });
});

describe('listErrorKeys', () => {
    const errors = {
        items: 'Agrega al menos un producto.',
        'items.0.quantity': 'La cantidad es obligatoria.',
        'items.2.color': 'El color es obligatorio.',
        items_note: 'Otro campo.',
        client_id: 'El cliente es obligatorio.',
    };

    it('returns the list key and the keys of its lines', () => {
        expect(listErrorKeys(errors, 'items')).toEqual([
            'items',
            'items.0.quantity',
            'items.2.color',
        ]);
    });

    it('does not take a field whose name only starts like the list', () => {
        expect(listErrorKeys(errors, 'items')).not.toContain('items_note');
    });

    it('returns nothing when the list has no errors', () => {
        expect(listErrorKeys(errors, 'details')).toEqual([]);
    });
});

describe('clearListErrors', () => {
    it('clears only the keys of the list', () => {
        const cleared: string[][] = [];
        clearListErrors(
            { 'items.1.quantity': 'Obligatoria.', client_id: 'Obligatorio.' },
            (...fields: string[]) => cleared.push(fields),
            'items',
        );

        expect(cleared).toEqual([['items.1.quantity']]);
    });

    it('does not call clearErrors when the list has no errors (it would clear the whole form)', () => {
        let calls = 0;
        clearListErrors({ client_id: 'Obligatorio.' }, () => calls++, 'items');

        expect(calls).toBe(0);
    });
});
