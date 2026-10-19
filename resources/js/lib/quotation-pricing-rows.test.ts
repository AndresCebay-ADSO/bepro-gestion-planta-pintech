import { describe, expect, it } from 'vitest';

import {
    applyPricingLines,
    pricingFieldsFor,
    pricingRequestFor,
    pricingSignature,
    splitPricedRows,
} from '@/lib/quotation-pricing-rows';
import type {
    PricingLine,
    QuotationPricingRow,
} from '@/lib/quotation-pricing-rows';

/*
 * PR 4b de la auditoría 3.6, revisión: el formulario adivinaba qué campo de precio escribió el usuario por cuál estaba
 * vacío. Llenaba un campo mientras se escribía, mostraba un ajuste distinto del guardado y pedía cada vista previa dos
 * veces. Ahora cada fila sabe qué campo manda (`pricing_source`) y la vista previa solo llena el otro.
 */

function row(
    overrides: Partial<QuotationPricingRow> = {},
): QuotationPricingRow {
    return {
        row_key: 'row-a',
        pricing_source: 'adjustment',
        product_id: '1',
        product_variant_id: '2',
        quantity: '5',
        list_unit_price: '',
        price_adjustment_pct: '15',
        unit_price: '',
        ...overrides,
    };
}

function line(
    target: QuotationPricingRow,
    overrides: Partial<PricingLine> = {},
): Record<string, PricingLine & { signature: string }> {
    return {
        [target.row_key]: {
            list_unit_price: '1000.0000',
            price_adjustment_pct: '15.00',
            unit_price: '1176',
            subtotal: '5880',
            ...overrides,
            signature: pricingSignature(target) ?? '',
        },
    };
}

describe('pricingFieldsFor', () => {
    it('con el ajuste al mando, manda solo el ajuste (con punto decimal)', () => {
        expect(
            pricingFieldsFor(
                row({ price_adjustment_pct: '-5,5', unit_price: '900' }),
            ),
        ).toEqual({ price_adjustment_pct: '-5.5', unit_price: null });
    });

    it('con el precio al mando, manda solo el precio', () => {
        expect(
            pricingFieldsFor(
                row({ pricing_source: 'price', unit_price: '1200' }),
            ),
        ).toEqual({ price_adjustment_pct: null, unit_price: '1200' });
    });

    it('una fila guardada sin tocar manda los dos', () => {
        expect(
            pricingFieldsFor(
                row({ pricing_source: 'saved', unit_price: '1176' }),
            ),
        ).toEqual({ price_adjustment_pct: '15', unit_price: '1176' });
    });
});

describe('pricingRequestFor', () => {
    it.each<[string, Partial<QuotationPricingRow>]>([
        ['sin presentación', { product_variant_id: '' }],
        ['sin cantidad', { quantity: '' }],
        ['cantidad en cero', { quantity: '0' }],
        ['cantidad con decimales', { quantity: '1,5' }],
    ])('%s: no se cotiza todavía', (_, overrides) => {
        expect(pricingRequestFor(row(overrides))).toBeNull();
    });

    it.each<[string, Partial<QuotationPricingRow>]>([
        ['el ajuste al mando, vacío', { price_adjustment_pct: '' }],
        ['el ajuste al mando, solo un signo', { price_adjustment_pct: '-' }],
        [
            'el precio al mando, vacío',
            { pricing_source: 'price', unit_price: '' },
        ],
    ])(
        '%s: se cotiza sin ajuste ni precio, al 0 % como al guardar',
        (_, overrides) => {
            expect(pricingRequestFor(row(overrides))).toMatchObject({
                price_adjustment_pct: null,
                unit_price: null,
            });
        },
    );

    // Lo que se ve es lo que se guarda: la vista previa manda los mismos campos de precio que el envío.
    it.each<[string, Partial<QuotationPricingRow>]>([
        ['ajuste al mando', { unit_price: '1176' }],
        ['ajuste al mando, vacío', { price_adjustment_pct: '' }],
        [
            'precio al mando',
            {
                pricing_source: 'price',
                price_adjustment_pct: '16.67',
                unit_price: '1200',
            },
        ],
        ['precio al mando, vacío', { pricing_source: 'price' }],
        ['fila guardada', { pricing_source: 'saved', unit_price: '1176' }],
    ])('%s: la vista previa y el guardado mandan lo mismo', (_, overrides) => {
        const current = row(overrides);

        expect(pricingRequestFor(current)).toMatchObject(
            pricingFieldsFor(current),
        );
    });

    it('una fila completa lleva producto, presentación, cantidad y el campo que manda', () => {
        expect(pricingRequestFor(row())).toEqual({
            product_id: '1',
            product_variant_id: '2',
            quantity: '5',
            price_adjustment_pct: '15',
            unit_price: null,
        });
    });

    it('la firma no cambia al llenar el campo que no manda: no se pide otra vista previa', () => {
        expect(pricingSignature(row({ unit_price: '1176' }))).toBe(
            pricingSignature(row()),
        );
    });
});

describe('applyPricingLines', () => {
    it('con el ajuste al mando llena el precio y el precio de lista, sin tocar el ajuste', () => {
        const current = row();

        expect(applyPricingLines([current], line(current))).toEqual([
            { ...current, list_unit_price: '1000.0000', unit_price: '1176' },
        ]);
    });

    it('con el precio al mando llena el ajuste', () => {
        const current = row({
            pricing_source: 'price',
            price_adjustment_pct: '',
            unit_price: '1200',
        });

        expect(
            applyPricingLines(
                [current],
                line(current, {
                    price_adjustment_pct: '16.67',
                    unit_price: '1200',
                }),
            ),
        ).toEqual([
            {
                ...current,
                list_unit_price: '1000.0000',
                price_adjustment_pct: '16.67',
            },
        ]);
    });

    it('en una fila guardada muestra el ajuste que el servidor recalculó, no el viejo', () => {
        const current = row({
            pricing_source: 'saved',
            price_adjustment_pct: '15',
            unit_price: '1176',
        });

        expect(
            applyPricingLines(
                [current],
                line(current, {
                    list_unit_price: '1100.0000',
                    price_adjustment_pct: '6.46',
                }),
            ),
        ).toEqual([
            {
                ...current,
                list_unit_price: '1100.0000',
                price_adjustment_pct: '6.46',
            },
        ]);
    });

    it('no toca una fila que cambió después de pedir el cálculo', () => {
        const asked = row();

        expect(
            applyPricingLines(
                [{ ...asked, price_adjustment_pct: '1' }],
                line(asked),
            ),
        ).toBeNull();
    });

    it('no toca el campo que el usuario está escribiendo aunque esté vacío', () => {
        const typing = row({
            pricing_source: 'price',
            price_adjustment_pct: '',
            unit_price: '',
        });

        // Sin precio se cotiza al 0 %: se muestra ese ajuste y el precio sigue vacío mientras se escribe.
        expect(
            applyPricingLines(
                [typing],
                line(typing, {
                    price_adjustment_pct: '0.00',
                    unit_price: '1000',
                }),
            ),
        ).toEqual([
            {
                ...typing,
                list_unit_price: '1000.0000',
                price_adjustment_pct: '0',
            },
        ]);
    });

    it('vuelve a llenar el campo vaciado si la edición vuelve a la misma firma', () => {
        const asked = row({ list_unit_price: '1000.0000', unit_price: '1176' });
        // «15» → «15,»: el ajuste vale lo mismo, así que no se pide otra vista previa, pero el precio ya se vació.
        const edited = {
            ...asked,
            price_adjustment_pct: '15,',
            unit_price: '',
        };

        expect(pricingSignature(edited)).toBe(pricingSignature(asked));
        expect(applyPricingLines([edited], line(asked))).toEqual([
            { ...edited, unit_price: '1176' },
        ]);
    });

    it('sigue a cada fila por su clave, no por su posición', () => {
        const first = row({ row_key: 'row-a' });
        const second = row({ row_key: 'row-b', quantity: '2' });
        const lines = {
            ...line(first),
            ...line(second, { subtotal: '2352' }),
        };

        // Se borró la primera fila: la segunda pasa a la posición 0 y conserva su cálculo.
        expect(applyPricingLines([second], lines)).toEqual([
            { ...second, list_unit_price: '1000.0000', unit_price: '1176' },
        ]);
    });

    it('devuelve null si nada cambia', () => {
        const filled = row({
            list_unit_price: '1000.0000',
            unit_price: '1176',
        });

        expect(applyPricingLines([filled], line(filled))).toBeNull();
    });
});

describe('splitPricedRows', () => {
    it('separa las filas que se cotizan de las incompletas, que no suman en los totales', () => {
        const complete = row({ row_key: 'row-a' });
        const withoutVariant = row({
            row_key: 'row-b',
            product_variant_id: '',
        });
        const withoutQuantity = row({ row_key: 'row-c', quantity: '' });

        const { priced, incomplete } = splitPricedRows([
            complete,
            withoutVariant,
            withoutQuantity,
        ]);

        expect(priced.map(({ rowKey }) => rowKey)).toEqual(['row-a']);
        expect(incomplete).toBe(2);
    });

    it('la firma de cada fila es la misma que pricingSignature', () => {
        const current = row({ pricing_source: 'saved', unit_price: '1176' });

        expect(splitPricedRows([current]).priced[0].signature).toBe(
            pricingSignature(current),
        );
    });

    it('sin filas incompletas no hay nada que avisar', () => {
        expect(splitPricedRows([row()]).incomplete).toBe(0);
    });
});
