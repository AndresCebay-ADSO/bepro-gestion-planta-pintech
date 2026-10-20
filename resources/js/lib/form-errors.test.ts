import { describe, expect, it } from 'vitest';

import { formErrorMessages } from '@/lib/form-errors';

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
