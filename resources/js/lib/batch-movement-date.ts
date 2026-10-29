import { isPlantDate } from '@/lib/plant-date';

/**
 * Fecha de un movimiento sobre un lote que ya existe: no puede ser anterior a la entrada del lote (el servidor la
 * rechaza, y el kardex lo mostraría antes de la entrada). La lógica pura de `useBatchMovementDate`.
 */

/**
 * La fecha mínima del movimiento: la más tardía entre el límite propio del formulario y la entrada del lote elegido.
 * Un valor que no es una fecha de planta se ignora; sin ninguno, no hay mínimo.
 */
export function batchMovementDateMin(
    batchEntryDate: string | null | undefined,
    ownMin?: string | null,
): string | undefined {
    return [ownMin, batchEntryDate]
        .filter((value): value is string => isPlantDate(value))
        .sort()
        .at(-1);
}

/**
 * Si la fecha elegida quedó antes de la entrada del lote (al cambiar de lote). Solo con dos fechas válidas: sin lote o
 * sin fecha no hay nada que comparar.
 */
export function isBeforeBatchEntry(
    movementDate: string | null | undefined,
    batchEntryDate: string | null | undefined,
): boolean {
    return (
        isPlantDate(movementDate) &&
        isPlantDate(batchEntryDate) &&
        movementDate < batchEntryDate
    );
}
