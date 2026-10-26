/**
 * Lógica pura de `<DataListing>` y `<RowActions>` (DESIGN.md §9), separada para probarla sin DOM.
 */

/** Papel de una columna en la tarjeta del celular. En el computador se ven todas. */
export type MobileRole = 'title' | 'subtitle' | 'status' | 'meta' | 'hidden';

/** Máximo de pares etiqueta-dato en la tarjeta del celular. */
export const MAX_MOBILE_META = 4;

/**
 * Problemas de una definición de columnas: exactamente un `title`, a lo sumo un `subtitle` y un `status`, hasta
 * `MAX_MOBILE_META` `meta` y claves sin repetir. Vacío si es válida.
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

    for (const role of ['subtitle', 'status'] as const) {
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
