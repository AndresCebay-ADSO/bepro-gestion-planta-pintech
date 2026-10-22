export type InventoryOption = {
    id: number;
    name?: string;
    code?: string;
    lot_number?: string;
    city?: string;
    type?: string;
    raw_material_id?: number | string;
    warehouse_id?: number | string;
    remaining_quantity?: string | number;
    unit_price?: number | string;
    /** Día en que entró el lote y su vencimiento (`Y-m-d`): se muestran en la opción para elegir el más antiguo. */
    entry_date?: string | null;
    expiry_date?: string | null;
    status?: string;
};
