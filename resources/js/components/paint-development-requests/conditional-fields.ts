/** Campos que el formulario solo pide según otra respuesta (`ContextStep`, `ApplicationStep`). */
const CONDITIONAL_FIELDS: Record<string, { source: string; value: string }> = {
    otro_sustrato: { source: 'sustrato', value: 'Otro' },
    sistema_existente: { source: 'pintura_existente', value: 'Sí' },
    otra_tecnologia: { source: 'tecnologia', value: 'Otra' },
};

/** Si la respuesta de origen no pide el campo, no aplica: «—» diría que falta un dato que nadie pidió. */
export function payloadFieldApplies(
    key: string,
    payload: Record<string, unknown>,
): boolean {
    const condition = CONDITIONAL_FIELDS[key];

    return (
        condition === undefined || payload[condition.source] === condition.value
    );
}
