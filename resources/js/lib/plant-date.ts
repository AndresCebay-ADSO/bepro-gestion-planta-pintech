/**
 * Fechas de planta (`YYYY-MM-DD`, el día en `America/Bogota`), la lógica pura de `<PlantDateInput>`. Son días del
 * calendario, no instantes: se comparan como texto (el formato ordena igual que la fecha) y nunca pasan por UTC.
 */

const PLANT_DATE = /^(\d{4})-(\d{2})-(\d{2})$/;

/** Años que muestra el selector cuando ningún límite los fija: desde 2021 hasta diez años adelante. */
const FIRST_YEAR = 2021;
const YEARS_AHEAD = 10;

export type PlantDateBoundsInput = {
    /** Hoy en la planta (`plantToday` de las props compartidas). */
    today: string;
    min?: string | null;
    max?: string | null;
    /** No después de hoy (fecha de un movimiento, de una entrega ya hecha). */
    notFuture?: boolean;
    /** No antes de hoy (fecha comprometida, vencimiento de una cotización). */
    notPast?: boolean;
};

export type PlantDateBounds = {
    min?: string;
    max?: string;
};

export function isPlantDate(value: string | null | undefined): value is string {
    if (!value) {
        return false;
    }

    const match = PLANT_DATE.exec(value);

    if (!match) {
        return false;
    }

    const date = toLocalDate(value);

    // `new Date(2026, 1, 30)` pasa al 2 de marzo: el día tiene que seguir siendo el mismo.
    return (
        date.getFullYear() === Number(match[1]) &&
        date.getMonth() === Number(match[2]) - 1 &&
        date.getDate() === Number(match[3])
    );
}

/** El día de planta como fecha local a medianoche (lo que usa el calendario). Sin validar: ver `parsePlantDate`. */
function toLocalDate(value: string): Date {
    const [year, month, day] = value.split('-').map(Number);

    return new Date(year, month - 1, day);
}

export function parsePlantDate(
    value: string | null | undefined,
): Date | undefined {
    return isPlantDate(value) ? toLocalDate(value) : undefined;
}

/** El día que el usuario tocó en el calendario, como fecha de planta. */
export function toPlantDate(date: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/**
 * Los límites efectivos: `notFuture` y `notPast` se suman a `min`/`max` y gana el más estricto. Un límite que no es una
 * fecha válida se ignora (el servidor sigue validando).
 */
export function plantDateBounds({
    today,
    min,
    max,
    notFuture = false,
    notPast = false,
}: PlantDateBoundsInput): PlantDateBounds {
    const mins = [isPlantDate(min) ? min : null, notPast ? today : null];
    const maxes = [isPlantDate(max) ? max : null, notFuture ? today : null];
    const effectiveMin = mins
        .filter((value): value is string => value !== null)
        .sort()
        .at(-1);
    const effectiveMax = maxes
        .filter((value): value is string => value !== null)
        .sort()
        .at(0);

    return {
        ...(effectiveMin ? { min: effectiveMin } : {}),
        ...(effectiveMax ? { max: effectiveMax } : {}),
    };
}

/**
 * Si el usuario puede elegir ese día. La fecha ya guardada (`saved`) siempre se puede conservar aunque hoy quede fuera
 * de los límites, como `PlantDate::allowing()` en el servidor: una entrega comprometida para ayer no obliga a cambiarla
 * al editar otro dato. Cualquier otro día tiene que caer dentro de los límites.
 */
export function isPlantDateSelectable(
    value: string,
    bounds: PlantDateBounds,
    saved?: string | null,
): boolean {
    if (saved && value === saved) {
        return true;
    }

    if (bounds.min && value < bounds.min) {
        return false;
    }

    if (bounds.max && value > bounds.max) {
        return false;
    }

    return true;
}

/**
 * Hasta dónde deja navegar el calendario, en meses (`YYYY-MM`): los que permiten los límites, ampliados para incluir
 * el valor actual y el guardado (un valor viejo tiene que poder verse). Sin límite: desde enero de 2021 hasta diciembre
 * de diez años después de hoy. El selector de año muestra los años de ese rango.
 */
export function plantDateMonthRange(
    bounds: PlantDateBounds,
    today: string,
    ...values: Array<string | null | undefined>
): { startMonth: string; endMonth: string } {
    const todayYear = isPlantDate(today)
        ? Number(today.slice(0, 4))
        : new Date().getFullYear();
    const shownMonths = values
        .filter((value): value is string => isPlantDate(value))
        .map((value) => value.slice(0, 7));
    const startMonth = bounds.min
        ? bounds.min.slice(0, 7)
        : `${Math.min(FIRST_YEAR, todayYear)}-01`;
    const endMonth = bounds.max
        ? bounds.max.slice(0, 7)
        : `${todayYear + YEARS_AHEAD}-12`;

    return {
        startMonth: [startMonth, ...shownMonths].sort().at(0)!,
        endMonth: [endMonth, ...shownMonths].sort().at(-1)!,
    };
}

/** El mes que abre el calendario: el del valor, si no el de hoy, llevado dentro de los límites. */
export function plantDateInitialMonth(
    value: string | null | undefined,
    bounds: PlantDateBounds,
    today: string,
): string {
    if (isPlantDate(value)) {
        return value.slice(0, 7);
    }

    const base = isPlantDate(today) ? today : toPlantDate(new Date());

    if (bounds.min && base < bounds.min) {
        return bounds.min.slice(0, 7);
    }

    if (bounds.max && base > bounds.max) {
        return bounds.max.slice(0, 7);
    }

    return base.slice(0, 7);
}
