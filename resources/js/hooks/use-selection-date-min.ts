import { useEffect } from 'react';

import { isBeforePlantDate, latestPlantDate } from '@/lib/plant-date';

type Options = {
    /**
     * La fecha del registro elegido, que la del formulario no puede preceder: la entrada del lote, el día en que se
     * completó la OP. Sin nada elegido, `undefined`.
     */
    selectedDate: string | null | undefined;
    /** Límite propio del formulario, que se suma al del registro elegido. */
    min?: string | null;
    /** La fecha del formulario. */
    value: string;
    /** Vacía la fecha del formulario. */
    clear: () => void;
};

/**
 * Una fecha que no puede ser anterior a la del registro elegido en el mismo formulario: devuelve el `min` del calendario
 * (la del registro, o el límite propio si es más tardío). Si al cambiar de registro la fecha queda antes de la suya, la
 * vacía: subirla sola cambiaría un dato sin aviso, y vacía el usuario ve que tiene que elegirla otra vez.
 */
export function useSelectionDateMin({
    selectedDate,
    min,
    value,
    clear,
}: Options): string | undefined {
    const beforeSelection = isBeforePlantDate(value, selectedDate);

    useEffect(() => {
        if (beforeSelection) {
            clear();
        }
    }, [beforeSelection, clear]);

    return latestPlantDate(min, selectedDate);
}
