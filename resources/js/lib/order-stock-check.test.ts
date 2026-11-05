import { describe, expect, it } from 'vitest';

import { readStockCheck } from '@/lib/order-stock-check';
import type { StockShortage } from '@/lib/order-stock-check';

const shortage: StockShortage = {
    code: 'S-7',
    unit: 'kg',
    required: '20.0000',
    available: '1.0000',
    missing: '19.0000',
};

const json = (body: unknown, status = 200) =>
    Promise.resolve(new Response(JSON.stringify(body), { status }));

describe('readStockCheck', () => {
    it('sin faltantes sigue con el envío', async () => {
        expect(await readStockCheck(json({ shortages: [] }))).toEqual({
            status: 'clear',
        });
    });

    it('con faltantes los entrega para el diálogo', async () => {
        expect(await readStockCheck(json({ shortages: [shortage] }))).toEqual({
            status: 'shortages',
            shortages: [shortage],
        });
    });

    it('con un 422 sigue con el envío, que muestra los errores de cada campo', async () => {
        expect(
            await readStockCheck(json({ errors: { quantity: ['x'] } }, 422)),
        ).toEqual({ status: 'clear' });
    });

    it.each([403, 419, 429, 500])(
        'con un %i no se crea la orden',
        async (status) => {
            expect(await readStockCheck(json({}, status))).toEqual({
                status: 'failed',
            });
        },
    );

    it('sin conexión no se crea la orden', async () => {
        expect(
            await readStockCheck(Promise.reject(new TypeError('sin red'))),
        ).toEqual({ status: 'failed' });
    });

    it('una respuesta que no es la esperada no se toma como «sin faltantes»', async () => {
        expect(await readStockCheck(json({ ok: true }))).toEqual({
            status: 'failed',
        });
        expect(
            await readStockCheck(
                Promise.resolve(new Response('<html></html>')),
            ),
        ).toEqual({ status: 'failed' });
    });
});
