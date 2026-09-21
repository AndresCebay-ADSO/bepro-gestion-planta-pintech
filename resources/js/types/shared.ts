/** Bodega en el selector de la cabecera (`warehouseContext`, HandleInertiaRequests). */
export type WarehouseOption = {
    id: number;
    name: string;
    city: string;
};

export type WarehouseContext = {
    current: WarehouseOption | null;
    available: WarehouseOption[];
};

/** Alerta nueva de la petición anterior (notificación emergente). */
export type NewAlert = {
    id: number;
    message: string;
    severity: string;
    type: string;
    type_label: string;
};

/** Alerta sin resolver de la campana de la cabecera. */
export type RecentAlert = {
    id: number;
    type: string;
    type_label: string;
    severity: string;
    severity_label: string;
    message: string;
    created_at: string | null;
    raw_material_code: string | null;
};
