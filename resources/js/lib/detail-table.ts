/**
 * Lógica pura de `<DetailTable>` (DESIGN.md §9), separada para probarla sin DOM.
 */

/**
 * Celdas de una fila de totales: la etiqueta ocupa las columnas anteriores a la primera que tiene valor (alineada a
 * la derecha, junto a la cifra) y después va una celda por columna. Sin ningún valor, la etiqueta ocupa la fila.
 * La primera columna es la que dice qué es la línea: un valor ahí no cabe con la etiqueta y se ignora.
 */
export function footerLayout(
    columnKeys: readonly string[],
    valueKeys: readonly string[],
): { labelSpan: number; cellKeys: string[] } {
    const firstValue = columnKeys.findIndex((key) => valueKeys.includes(key));
    const labelSpan =
        firstValue === -1 ? columnKeys.length : Math.max(1, firstValue);

    return { labelSpan, cellKeys: columnKeys.slice(labelSpan) };
}
