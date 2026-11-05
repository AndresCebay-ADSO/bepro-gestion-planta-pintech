/** Una materia prima que no alcanza para la orden. Las cantidades llegan como texto, en la unidad de la materia prima. */
export type StockShortage = {
    code: string;
    unit: string;
    required: string;
    available: string;
    missing: string;
};

export type StockCheckResult =
    | { status: 'clear' }
    | { status: 'shortages'; shortages: StockShortage[] }
    | { status: 'failed' };

/**
 * Qué hacer con la respuesta de la revisión de stock. Un 422 no es un fallo: son las mismas reglas de crear la orden, así
 * que el envío normal vuelve a recibir esos errores y los muestra bajo cada campo. Cualquier otra cosa que no sea la
 * respuesta esperada (sin conexión, error del servidor, sesión vencida) cuenta como fallo: sin revisión no se crea.
 */
export async function readStockCheck(
    request: Promise<Response>,
): Promise<StockCheckResult> {
    try {
        const response = await request;

        if (response.status === 422) {
            return { status: 'clear' };
        }

        if (!response.ok) {
            return { status: 'failed' };
        }

        const { shortages } = (await response.json()) as {
            shortages?: StockShortage[];
        };

        if (!Array.isArray(shortages)) {
            return { status: 'failed' };
        }

        return shortages.length === 0
            ? { status: 'clear' }
            : { status: 'shortages', shortages };
    } catch {
        return { status: 'failed' };
    }
}
