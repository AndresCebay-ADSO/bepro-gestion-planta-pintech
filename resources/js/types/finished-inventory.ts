import type { Paginated } from '@/types/ui';

export type FinishedMovementType = 'entry' | 'exit';

export type FinishedMovementReason =
    | 'production'
    | 'return'
    | 'adjustment'
    | 'sale'
    | 'sample'
    | 'transfer'
    | 'transformation'
    | 'deterioration';

export type FinishedReasonOption = {
    value: FinishedMovementReason;
    label: string;
};

export type FinishedProductOption = {
    id: number;
    code: string | null;
    name: string;
};

export type FinishedVariantOption = {
    id: number;
    code: string | null;
    name: string;
    presentation_label: string | null;
};

export type FinishedWarehouseOption = {
    id: number;
    name: string;
    city: string | null;
    type?: string;
};

export type FinishedBatchStock = {
    warehouse_id: number;
    quantity: number;
};

export type FinishedBatchOption = {
    id: number;
    product_id?: number;
    product_variant_id?: number | null;
    product?: FinishedProductOption | null;
    product_variant?: FinishedVariantOption | null;
    variant?: FinishedVariantOption | null;
    /** Número de lote de la OP que produjo el lote: con la presentación lo identifica (B55). */
    lot_number: number;
    /** Color que pidió el cliente en esa OP (3.4). */
    color: string | null;
    entry_date: string | null;
    initial_quantity: number;
    stocks?: FinishedBatchStock[];
};

export type FinishedInventoryMovement = {
    id: number;
    product_id: number;
    product_variant_id: number | null;
    warehouse_id: number;
    production_order_id: number | null;
    finished_product_batch_id: number | null;
    type: FinishedMovementType;
    reason: FinishedMovementReason;
    quantity: number;
    cost_price?: string | number | null;
    movement_date: string;
    notes?: string | null;
    product?: FinishedProductOption | null;
    product_variant?: FinishedVariantOption | null;
    batch?: {
        id: number;
        entry_date: string | null;
        initial_quantity?: number;
        production_order: {
            id: number;
            lot_number: number;
            color: string | null;
        };
    } | null;
    warehouse?: FinishedWarehouseOption | null;
    production_order?: {
        id: number;
        order_number: string;
    } | null;
    created_by?: {
        id: number;
        name: string;
    } | null;
};

export type FinishedMovementsPage = {
    movements: Paginated<FinishedInventoryMovement>;
    batches?: FinishedBatchOption[];
    warehouses?: FinishedWarehouseOption[];
    warehouseOptions: { value: string; label: string }[];
    typeOptions: { value: string; label: string }[];
    reasonOptions: { value: string; label: string }[];
    /** Razones que acepta el servidor para una entrada y una salida a mano (el traslado tiene su propio cajón). */
    entryReasonOptions: FinishedReasonOption[];
    exitReasonOptions: FinishedReasonOption[];
    currentWarehouseId?: number | null;
    filters: {
        search?: string;
        type?: string;
        reason?: string;
        warehouse_id?: string | number;
        date_from?: string;
        date_to?: string;
    };
    can: {
        create: boolean;
    };
};
