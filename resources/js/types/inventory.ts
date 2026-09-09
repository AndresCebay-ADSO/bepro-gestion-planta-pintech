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
    status?: string;
};
