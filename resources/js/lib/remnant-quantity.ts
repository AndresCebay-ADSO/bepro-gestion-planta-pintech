import { parseDecimal } from '@/lib/numeric-input';

type FormValue = string | number | null | undefined;

/**
 * Galones de un sobrante pesado, solo para mostrar: el servidor calcula con bcmath el valor que se guarda y este nunca
 * se envía. `null` si no hay kilos mayores a 0 o no hay peso por galón.
 */
export function remnantGallonsForDisplay(
    kilos: FormValue,
    density: FormValue,
): number | null {
    const parsedKilos = parseDecimal(kilos);
    const parsedDensity = parseDecimal(density);

    if (
        parsedKilos === null ||
        parsedDensity === null ||
        parsedKilos <= 0 ||
        parsedDensity <= 0
    ) {
        return null;
    }

    return parsedKilos / parsedDensity;
}
