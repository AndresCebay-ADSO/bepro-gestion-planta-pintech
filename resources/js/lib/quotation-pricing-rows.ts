import { toDecimalPayload } from '@/lib/numeric-input';

/**
 * Cuál de los dos campos de precio escribió el usuario: ese manda y el otro lo calcula el servidor. `saved` es una fila
 * que llega guardada y nadie ha tocado: se mandan los dos y el servidor conserva el precio (y el ajuste, si aún lo da).
 */
export type PricingSource = 'adjustment' | 'price' | 'saved';

export type QuotationPricingRow = {
    /** Identidad estable de la fila: los índices cambian al borrar una. */
    row_key: string;
    pricing_source: PricingSource;
    product_id: string;
    product_variant_id: string;
    quantity: string;
    list_unit_price: string;
    price_adjustment_pct: string;
    unit_price: string;
};

export type PricingFields = {
    price_adjustment_pct: string | null;
    unit_price: string | null;
};

export type PricingLine = {
    list_unit_price: string;
    price_adjustment_pct: string;
    unit_price: string;
    subtotal: string;
};

let lastRowKey = 0;

export function newRowKey(): string {
    lastRowKey += 1;

    return `row-${lastRowKey}`;
}

/** Los campos de precio que se mandan al servidor, al guardar y en la vista previa: solo el que manda. */
export function pricingFieldsFor(row: QuotationPricingRow): PricingFields {
    const adjustment = toDecimalPayload(row.price_adjustment_pct) || null;
    const unitPrice = row.unit_price || null;

    if (row.pricing_source === 'adjustment') {
        return { price_adjustment_pct: adjustment, unit_price: null };
    }

    if (row.pricing_source === 'price') {
        return { price_adjustment_pct: null, unit_price: unitPrice };
    }

    return { price_adjustment_pct: adjustment, unit_price: unitPrice };
}

/**
 * Lo que se le pide al servidor por una fila, o null si todavía no se puede cotizar: le falta la presentación o una
 * cantidad entera mayor que cero. Los campos de precio son los mismos que al guardar (`pricingFieldsFor`): si el que
 * manda está vacío viaja vacío y el servidor cotiza al 0 %, igual que al guardar, así que lo que se ve es lo que queda.
 */
export function pricingRequestFor(row: QuotationPricingRow) {
    if (
        row.product_id === '' ||
        row.product_variant_id === '' ||
        !/^\d+$/.test(row.quantity) ||
        Number(row.quantity) <= 0
    ) {
        return null;
    }

    return {
        product_id: row.product_id,
        product_variant_id: row.product_variant_id,
        quantity: row.quantity,
        ...pricingFieldsFor(row),
    };
}

export type PricedRow = {
    rowKey: string;
    body: NonNullable<ReturnType<typeof pricingRequestFor>>;
    /** El cuerpo serializado: la misma firma que `pricingSignature`, armada una sola vez. */
    signature: string;
};

/**
 * Las filas que se le piden al servidor y cuántas quedan fuera por incompletas (sin presentación o sin una cantidad
 * entera mayor que cero): esas no suman en los totales de la vista previa, y al guardar el servidor las rechaza.
 */
export function splitPricedRows(rows: QuotationPricingRow[]): {
    priced: PricedRow[];
    incomplete: number;
} {
    const priced = rows.flatMap((row): PricedRow[] => {
        const body = pricingRequestFor(row);

        return body === null
            ? []
            : [{ rowKey: row.row_key, body, signature: JSON.stringify(body) }];
    });

    return { priced, incomplete: rows.length - priced.length };
}

/** Lo que identifica el cálculo de una fila: una respuesta solo se aplica a la fila tal como estaba al pedirla. */
export function pricingSignature(row: QuotationPricingRow): string | null {
    const request = pricingRequestFor(row);

    return request === null ? null : JSON.stringify(request);
}

/**
 * Pone en cada fila lo que calculó el servidor: el campo que no manda y el precio de lista. Nunca toca el que escribió
 * el usuario. Devuelve null si nada cambia, para no disparar otro render.
 *
 * @param lines por `row_key`, con la firma de la fila para la que se calcularon
 */
export function applyPricingLines<TRow extends QuotationPricingRow>(
    rows: TRow[],
    lines: Record<string, PricingLine & { signature: string }>,
): TRow[] | null {
    let changed = false;

    const next = rows.map((row) => {
        const line = lines[row.row_key];

        // La fila cambió después de pedir este cálculo: la respuesta nueva ya viene en camino.
        if (line === undefined || line.signature !== pricingSignature(row)) {
            return row;
        }

        const filled =
            row.pricing_source === 'adjustment'
                ? { ...row, unit_price: String(Number(line.unit_price)) }
                : {
                      ...row,
                      price_adjustment_pct: String(
                          Number(line.price_adjustment_pct),
                      ),
                  };

        filled.list_unit_price = line.list_unit_price;

        if (
            filled.unit_price === row.unit_price &&
            filled.price_adjustment_pct === row.price_adjustment_pct &&
            filled.list_unit_price === row.list_unit_price
        ) {
            return row;
        }

        changed = true;

        return filled;
    });

    return changed ? next : null;
}
