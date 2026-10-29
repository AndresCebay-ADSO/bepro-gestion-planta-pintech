/**
 * Pasos del formulario de solicitud de desarrollo (I+D). Cada paso guarda sus datos en su propio grupo
 * (`context_payload.temp_min`); los datos del proyecto (`client_name`, `sample_due_date`…) van en el primero.
 */
const PAYLOAD_STEPS: Record<string, number> = {
    context_payload: 1,
    performance_payload: 2,
    application_payload: 3,
    specifications_payload: 4,
};

/** El paso donde está el campo de una llave de error. */
export function stepOfErrorKey(key: string): number {
    return PAYLOAD_STEPS[key.split('.')[0]] ?? 1;
}

/**
 * El primer paso con un error: allí tiene que ir el formulario para que el usuario vea el campo y su mensaje (solo se
 * pinta el paso actual). Sin errores, `undefined`.
 */
export function firstErrorStep(keys: readonly string[]): number | undefined {
    if (keys.length === 0) {
        return undefined;
    }

    return Math.min(...keys.map(stepOfErrorKey));
}
