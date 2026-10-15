import { toDecimalPayload } from '@/lib/numeric-input';

/** Números técnicos de la solicitud, como `bloque.campo`: vacíos se guardan como null. */
export const NUMERIC_PAYLOAD_FIELDS = new Set([
    'context_payload.temp_min',
    'context_payload.temp_max',
    'application_payload.temp_aplicacion',
    'application_payload.humedad_aplicacion',
    'application_payload.dft_min',
    'application_payload.dft_max',
    'application_payload.manos',
    'application_payload.pot_life',
    'application_payload.solidos_vol',
    'application_payload.voc',
    'performance_payload.adherencia_obj',
    'performance_payload.camara_obj',
    'performance_payload.delta_e',
]);

/** Los números técnicos se escriben con coma o punto: se envían con punto, y vacíos como null. */
export function withNumericPayloads<T extends object>(formData: T): T {
    const converted = { ...formData } as Record<string, unknown>;

    for (const key of NUMERIC_PAYLOAD_FIELDS) {
        const [payloadKey, field] = key.split('.');
        const payload = converted[payloadKey] as
            Record<string, unknown> | undefined;
        const value = payload?.[field];

        if (value === null || value === undefined) {
            continue;
        }

        const payloadValue = toDecimalPayload(value as string | number);

        converted[payloadKey] = {
            ...payload,
            [field]: payloadValue === '' ? null : payloadValue,
        };
    }

    return converted as T;
}
