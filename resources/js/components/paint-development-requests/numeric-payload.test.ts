import { describe, expect, it } from 'vitest';

import { withNumericPayloads } from '@/components/paint-development-requests/numeric-payload';

describe('withNumericPayloads: números técnicos de la solicitud de I+D', () => {
    it('envía con punto, conserva el signo y deja el texto libre igual', () => {
        expect(
            withNumericPayloads({
                client_name: 'Cliente 1,5',
                context_payload: {
                    temp_min: '-5,5',
                    temp_max: '120',
                    sustrato: 'Acero, galvanizado',
                },
                application_payload: { manos: '2', dft_min: ',5', voc: null },
                performance_payload: { delta_e: '1,0' },
            }),
        ).toEqual({
            client_name: 'Cliente 1,5',
            context_payload: {
                temp_min: '-5.5',
                temp_max: '120',
                sustrato: 'Acero, galvanizado',
            },
            application_payload: { manos: '2', dft_min: '0.5', voc: null },
            performance_payload: { delta_e: '1.0' },
        });
    });

    it('un signo o separador solo se envía como null', () => {
        expect(
            withNumericPayloads({
                context_payload: { temp_min: '-', temp_max: ',' },
            }),
        ).toEqual({ context_payload: { temp_min: null, temp_max: null } });
    });

    it('un texto que no es número llega tal cual al servidor', () => {
        expect(
            withNumericPayloads({ application_payload: { manos: '3,5,1' } }),
        ).toEqual({ application_payload: { manos: '3,5,1' } });
    });
});
