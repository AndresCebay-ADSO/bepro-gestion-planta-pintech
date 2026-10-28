/**
 * Lógica pura de `<DataListing>` y `<RowActions>` (DESIGN.md §9), separada para probarla sin DOM.
 */

/** Papel de una columna en la tarjeta del celular. En el computador se ven todas. */
export type MobileRole =
    'title' | 'subtitle' | 'status' | 'primary' | 'meta' | 'hidden';

/** Máximo de pares etiqueta-dato en la tarjeta del celular (sin contar el `primary`). */
export const MAX_MOBILE_META = 4;

/**
 * Problemas de una definición de columnas: exactamente un `title`, a lo sumo un `subtitle`, un `status` y un
 * `primary`, hasta `MAX_MOBILE_META` `meta` y claves sin repetir. Vacío si es válida.
 */
export function listingColumnProblems(
    columns: readonly { key: string; mobile: MobileRole }[],
): string[] {
    const problems: string[] = [];
    const count = (role: MobileRole) =>
        columns.filter((column) => column.mobile === role).length;

    if (count('title') !== 1) {
        problems.push(
            `Debe haber exactamente una columna «title» (hay ${count('title')}).`,
        );
    }

    for (const role of ['subtitle', 'status', 'primary'] as const) {
        if (count(role) > 1) {
            problems.push(
                `Solo puede haber una columna «${role}» (hay ${count(role)}).`,
            );
        }
    }

    if (count('meta') > MAX_MOBILE_META) {
        problems.push(
            `Como máximo ${MAX_MOBILE_META} columnas «meta» (hay ${count('meta')}); las demás van «hidden».`,
        );
    }

    const keys = columns.map((column) => column.key);
    const repeated = keys.filter((key, index) => keys.indexOf(key) !== index);

    if (repeated.length > 0) {
        problems.push(
            `Claves de columna repetidas: ${[...new Set(repeated)].join(', ')}.`,
        );
    }

    return problems;
}

/**
 * Celda sin dato: `null`, `undefined`, `false` o el texto vacío. La tarjeta omite su par etiqueta-dato y la tabla
 * muestra «—». El 0 sí es un dato.
 */
export function isEmptyCell(
    value: unknown,
): value is null | undefined | false | '' {
    return (
        value === null || value === undefined || value === false || value === ''
    );
}

/**
 * Pares etiqueta-dato de la tarjeta del celular: el `primary` primero, luego los `meta` en su orden; los que no
 * tienen dato no aparecen.
 */
export function cardPairs<Row, Value, Column extends { mobile: MobileRole }>(
    columns: readonly (Column & { cell: (row: Row) => Value })[],
    row: Row,
): { column: Column; value: Value }[] {
    return [
        ...columns.filter((column) => column.mobile === 'primary'),
        ...columns.filter((column) => column.mobile === 'meta'),
    ].flatMap((column) => {
        const value = column.cell(row);

        return isEmptyCell(value) ? [] : [{ column, value }];
    });
}

/**
 * Acciones que se muestran en el menú «⋯»: sin las ocultas, las normales en su orden y las destructivas al final.
 */
export function visibleRowActions<
    T extends { hidden?: boolean; destructive?: boolean },
>(actions: readonly T[]): { regular: T[]; destructive: T[] } {
    const visible = actions.filter((action) => !action.hidden);

    return {
        regular: visible.filter((action) => !action.destructive),
        destructive: visible.filter((action) => action.destructive),
    };
}
