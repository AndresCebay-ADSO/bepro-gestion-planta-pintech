import { parseDecimal, toDecimalPayload } from '@/lib/numeric-input';

/**
 * Consumo real de un ingrediente de la OP. El formulario lo muestra en la unidad de la fórmula y el servidor lo guarda
 * en la unidad de la materia prima; `conversionFactor` pasa de la primera a la segunda. Una sola fuente para el envío
 * del formulario y para la vista previa de costos.
 */

const QUANTITY_SCALE = 4;

function roundToScale(value: number): string {
    return Number(value.toFixed(QUANTITY_SCALE)).toString();
}

/** Valor inicial del campo, a partir de lo guardado (en la unidad de la materia prima). */
export function ingredientQuantityForInput(
    storedQuantity: string | number,
    conversionFactor?: number | null,
): string {
    const quantity = parseDecimal(storedQuantity);

    if (quantity === null) {
        return String(storedQuantity);
    }

    return roundToScale(
        conversionFactor ? quantity / conversionFactor : quantity,
    );
}

/** Lo que se envía al servidor, a partir de lo que escribió el operario (`"0,8"`). */
export function ingredientQuantityForServer(
    value: string | number,
    conversionFactor?: number | null,
): string {
    const payload = toDecimalPayload(value);
    const quantity = parseDecimal(payload);

    if (quantity === null || !conversionFactor) {
        return payload;
    }

    return roundToScale(quantity * conversionFactor);
}
