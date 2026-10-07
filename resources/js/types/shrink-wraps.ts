/** Registro de termoencogido (3.8), tal como lo envía ShrinkWrapController. */
export type ShrinkWrapRow = {
    id: number;
    wrapped_at: string;
    applications: number;
    order: {
        id: number;
        order_number: string;
        lot_number: number;
        product_name: string | null;
    };
    type_name: string;
    warehouse_name: string;
    created_by_name: string;
    /** Solo con `costs.view`. */
    total_cost: string | null;
};

export type ShrinkWrapItemRow = {
    id: number;
    code: string;
    unit_symbol: string;
    quantity_per_application: string;
    quantity: string;
    /** Solo con `costs.view`. */
    total_cost: string | null;
};

/** OP completada que se puede elegir al registrar. */
export type ShrinkWrapOrderOption = {
    value: number;
    order_number: string;
    lot_number: number;
    product_name: string | null;
    warehouse_name: string | null;
    completion_date: string | null;
};

/** Tipo activo con su receta por aplicación. */
export type ShrinkWrapTypeOption = {
    value: number;
    label: string;
    items: { code: string; unit_symbol: string; quantity: string }[];
};

export type ShrinkWrapFormData = {
    production_order_id: string;
    shrink_wrap_type_id: string;
    applications: string;
    wrapped_at: string;
    notes: string;
};
