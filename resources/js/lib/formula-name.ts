/**
 * El nombre de una fórmula en las migas: «Esmalte blanco v2», con el producto, porque «v2» solo no dice de qué
 * producto es. Sin producto (no debería pasar: toda fórmula tiene uno), solo la versión.
 */
export function formulaCrumb(formula: {
    version: number;
    product?: { name: string } | null;
}): string {
    return formula.product
        ? `${formula.product.name} v${formula.version}`
        : `v${formula.version}`;
}
