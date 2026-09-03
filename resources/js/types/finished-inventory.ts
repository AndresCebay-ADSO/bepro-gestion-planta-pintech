import type { PaginationLink } from '@/types/ui';

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
    quantity: string | number;
};

export type FinishedBatchOption = {
    id: number;
    product_id?: number;
    product_variant_id?: number | null;
    product?: FinishedProductOption | null;
    product_variant?: FinishedVariantOption | null;
    variant?: FinishedVariantOption | null;
    entry_date: string | null;
    initial_quantity: string | number;
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
    quantity: string | number;
    cost_price?: string | number | null;
    movement_date: string;
    notes?: string | null;
    product?: FinishedProductOption | null;
    product_variant?: FinishedVariantOption | null;
    batch?: {
        id: number;
        entry_date: string | null;
        initial_quantity?: string | number;
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
    movements: {
        data: FinishedInventoryMovement[];
        links: PaginationLink[];
    };
    batches?: FinishedBatchOption[];
    warehouses?: FinishedWarehouseOption[];
    warehouseOptions: { value: string; label: string }[];
    typeOptions: { value: string; label: string }[];
    reasonOptions: { value: string; label: string }[];
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

export const finishedMovementReasonLabels: Record<
    FinishedMovementReason,
    string
> = {
    production: 'Producción',
    return: 'Devolución',
    adjustment: 'Ajuste',
    sale: 'Venta',
    sample: 'Muestra',
    transfer: 'Traslado',
    transformation: 'Transformación',
    deterioration: 'Deterioro',
};

export const entryReasons: Array<{
    value: FinishedMovementReason;
    label: string;
}> = [
    { value: 'return', label: 'Devolución' },
    { value: 'adjustment', label: 'Ajuste' },
];

export const exitReasons: Array<{
    value: FinishedMovementReason;
    label: string;
}> = [
    { value: 'sale', label: 'Venta' },
    { value: 'sample', label: 'Muestra' },
    { value: 'deterioration', label: 'Deterioro' },
    { value: 'transformation', label: 'Transformación' },
    { value: 'adjustment', label: 'Ajuste' },
];
