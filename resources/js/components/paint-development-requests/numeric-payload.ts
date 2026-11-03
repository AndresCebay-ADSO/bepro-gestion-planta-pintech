import { isEmptyNumber } from '@/lib/formatters';
import { withDecimalPayloads } from '@/lib/numeric-input';

/** Números técnicos de la solicitud, por bloque del JSON. */
const NUMERIC_FIELDS_BY_PAYLOAD = {
    context_payload: ['temp_min', 'temp_max'],
    application_payload: [
        'temp_aplicacion',
        'humedad_aplicacion',
        'dft_min',
        'dft_max',
        'manos',
        'pot_life',
        'solidos_vol',
        'voc',
    ],
    performance_payload: ['adherencia_obj', 'camara_obj', 'delta_e'],
} as const;

type NumericPayloadKey = keyof typeof NUMERIC_FIELDS_BY_PAYLOAD;

/** Los mismos campos como `bloque.campo`: vacíos se guardan como null. */
export const NUMERIC_PAYLOAD_FIELDS = new Set(
    Object.entries(NUMERIC_FIELDS_BY_PAYLOAD).flatMap(([payloadKey, fields]) =>
        fields.map((field) => `${payloadKey}.${field}`),
    ),
);

/**
 * Si la respuesta guardada se pinta como número: solo un número técnico de ese bloque que de verdad sea un número. Un
 * texto que no lo sea sigue como texto, para no esconderlo detrás de un «—».
 */
export function isNumericPayloadValue(
    payloadKey: string,
    field: string,
    value: unknown,
): value is number | string {
    return (
        NUMERIC_PAYLOAD_FIELDS.has(`${payloadKey}.${field}`) &&
        (typeof value === 'number' || typeof value === 'string') &&
        !isEmptyNumber(value)
    );
}

/** Los números técnicos se escriben con coma o punto: se envían con punto, y vacíos como null. */
export function withNumericPayloads<
    T extends Partial<Record<NumericPayloadKey, Record<string, unknown>>>,
>(formData: T): T {
    const converted = { ...formData };

    for (const payloadKey of Object.keys(
        NUMERIC_FIELDS_BY_PAYLOAD,
    ) as NumericPayloadKey[]) {
        const payload = formData[payloadKey];

        if (!payload) {
            continue;
        }

        const fields = NUMERIC_FIELDS_BY_PAYLOAD[payloadKey].filter(
            (field) => field in payload,
        );
        const withDots = withDecimalPayloads(payload, fields);

        for (const field of fields) {
            if (withDots[field] === '') {
                withDots[field] = null;
            }
        }

        converted[payloadKey] = withDots as T[NumericPayloadKey];
    }

    return converted;
}
