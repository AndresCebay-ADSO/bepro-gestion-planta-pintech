/**
 * Un lote de producto terminado se identifica con el número de lote de su OP; si la OP lleva el color que pidió el
 * cliente, va junto al lote para que el sobrante de un «RAL 3020» no se despache como el producto base (3.4).
 * Ejemplo: «1042 · RAL 3020».
 */
export function finishedLotLabel(
    lotNumber: number,
    color?: string | null,
): string {
    return color ? `${lotNumber} · ${color}` : String(lotNumber);
}
