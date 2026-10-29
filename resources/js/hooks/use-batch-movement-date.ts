import { useEffect } from 'react';

import {
    batchMovementDateMin,
    isBeforeBatchEntry,
} from '@/lib/batch-movement-date';

type Options = {
    /** `entry_date` del lote elegido; sin lote, `undefined`. */
    batchEntryDate: string | null | undefined;
    /** La fecha del movimiento en el formulario. */
    movementDate: string;
    /** Vacía la fecha del movimiento. */
    clearMovementDate: () => void;
    /** Límite propio del formulario, que se suma al del lote. */
    min?: string | null;
};

/**
 * Fecha de un movimiento sobre un lote que ya existe: devuelve el `min` del calendario (la entrada del lote, o el límite
 * propio del formulario si es más tardío). Si al cambiar de lote la fecha elegida queda antes de su entrada, la vacía:
 * subirla sola cambiaría un dato sin aviso, y vacía el usuario ve que tiene que elegirla otra vez.
 */
export function useBatchMovementDate({
    batchEntryDate,
    movementDate,
    clearMovementDate,
    min,
}: Options): string | undefined {
    const beforeEntry = isBeforeBatchEntry(movementDate, batchEntryDate);

    useEffect(() => {
        if (beforeEntry) {
            clearMovementDate();
        }
    }, [beforeEntry, clearMovementDate]);

    return batchMovementDateMin(batchEntryDate, min);
}
