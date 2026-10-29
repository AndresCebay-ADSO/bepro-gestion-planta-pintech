import { describe, expect, it } from 'vitest';

import {
    errorSelectorsFor,
    FALLBACK_SELECTORS,
    orderedErrorKeys,
} from '@/lib/focus-first-error';

/*
 * Auditoría 3.6, INV-11: después de un 422 el foco no se movía al primer campo con error; en un cajón, un error arriba
 * podía quedar fuera de vista. Aquí solo la parte pura (qué campo buscar); mover el foco necesita el DOM y se prueba en
 * el navegador.
 */

describe('orderedErrorKeys: los campos con error, en el orden en que llegaron', () => {
    it('conserva el orden y deja fuera los vacíos', () => {
        expect(
            orderedErrorKeys({
                quantity: 'La cantidad es obligatoria.',
                notes: undefined,
                cost_price: '',
                lot_number: 'Debes indicar el número de lote.',
            }),
        ).toEqual(['quantity', 'lot_number']);
    });

    it('sin errores no hay campo', () => {
        expect(orderedErrorKeys({})).toEqual([]);
    });
});

describe('errorSelectorsFor: dónde buscar el campo de un error', () => {
    it('un campo simple se busca por id y por name', () => {
        expect(errorSelectorsFor('quantity')).toEqual([
            '[id="quantity"]',
            '[name="quantity"]',
        ]);
    });

    it('un campo de una fila busca primero la fila y después el campo que la contiene', () => {
        expect(errorSelectorsFor('items.2.quantity')).toEqual([
            '[id="items.2.quantity"]',
            '[name="items.2.quantity"]',
            '[id="items"]',
            '[name="items"]',
        ]);
    });

    it('un valor de una lista dentro de un grupo busca el grupo que la contiene', () => {
        expect(errorSelectorsFor('application_payload.metodo.0')).toEqual([
            '[id="application_payload.metodo.0"]',
            '[name="application_payload.metodo.0"]',
            '[id="application_payload.metodo"]',
            '[name="application_payload.metodo"]',
        ]);
    });

    it('un campo de un grupo sin posición conserva la búsqueda por la raíz', () => {
        expect(errorSelectorsFor('context_payload.temp_min')).toEqual([
            '[id="context_payload.temp_min"]',
            '[name="context_payload.temp_min"]',
            '[id="context_payload"]',
            '[name="context_payload"]',
        ]);
    });

    it('escapa las comillas del nombre', () => {
        expect(errorSelectorsFor('a"b')).toEqual([
            '[id="a\\"b"]',
            '[name="a\\"b"]',
        ]);
    });
});

describe('FALLBACK_SELECTORS: qué mensaje se busca cuando ningún campo coincide', () => {
    it('busca primero el error de un campo y después un aviso de la página', () => {
        // Un `Alert` de la página (el aviso de una carrera perdida) suele estar arriba de todo: si fuera primero, el
        // foco se iría allí y no al diálogo o al campo que hay que corregir.
        expect(FALLBACK_SELECTORS).toEqual([
            'p.text-destructive',
            '[role="alert"]',
        ]);
    });
});
