/**
 * El nombre de una cotización en títulos y migas: «Cotización No. 57». Sin número (no debería pasar: se asigna al
 * crearla), con su `id`, como el título de la edición.
 */
export function quotationName(quotation: {
    id: number;
    quotation_number: number | null;
}): string {
    return `Cotización No. ${quotation.quotation_number ?? quotation.id}`;
}
