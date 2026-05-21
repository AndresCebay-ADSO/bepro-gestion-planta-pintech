import type { FormDataErrors } from '@inertiajs/core';
import type { SetDataAction } from '@inertiajs/react';

export type ProductionOrderStatus = 'pending' | 'in_progress' | 'completed' | 'cancelled';

export type FormNumberValue = string | number;

export type VariantOption = {
    id: number;
    sku: string;
    presentation_label: string;
    presentation_value: number;
};

export type RawMaterialOption = {
    id: number;
    label: string;
};

export type ProductionOrderRawMaterial = {
    code?: string | null;
};

export type ProductionOrderDetail = {
    id: number;
    raw_material?: ProductionOrderRawMaterial | null;
    planned_quantity: FormNumberValue;
    actual_quantity?: FormNumberValue | null;
    unit_cost?: number | null;
    total_cost?: number | null;
};

export type ProductionOrderProductVariant = {
    presentation_label?: string | null;
    presentation_value?: number | null;
};

export type ProductionOrderPackagingPlan = {
    id: number;
    product_variant?: ProductionOrderProductVariant | null;
    planned_units: FormNumberValue;
    actual_units?: FormNumberValue | null;
    cost_price?: number | null;
};

export type ProductionOrderLineAdjustment = {
    id: number;
    raw_material?: ProductionOrderRawMaterial | null;
    quantity: FormNumberValue;
    reason: string;
};

export type ProductionOrder = {
    id: number;
    order_number: string;
    status: ProductionOrderStatus;
    quantity: FormNumberValue;
    actual_quantity?: FormNumberValue | null;
    viscosity_ku?: FormNumberValue | null;
    grinding_hg?: FormNumberValue | null;
    quality_solids?: FormNumberValue | null;
    agitation_start_time?: string | null;
    agitation_end_time?: string | null;
    packaging_start_time?: string | null;
    packaging_end_time?: string | null;
    responsible_name?: string | null;
    spillage_quantity?: FormNumberValue | null;
    notes?: string | null;
    completion_date?: string | null;
    total_bulk_cost?: FormNumberValue | null;
    total_finished_cost?: FormNumberValue | null;
    qr_landing_url?: string | null;
    qr_image_url?: string | null;
    product?: {
        name?: string | null;
        profit_margin?: FormNumberValue | null;
        quality_solids_lower?: FormNumberValue | null;
        quality_solids_upper?: FormNumberValue | null;
    } | null;
    formula?: {
        version?: FormNumberValue | null;
    } | null;
    warehouse?: {
        name?: string | null;
    } | null;
    planned_date?: string | null;
    details?: ProductionOrderDetail[];
    packaging_plans?: ProductionOrderPackagingPlan[];
    line_adjustments?: ProductionOrderLineAdjustment[];
};

export type ProductionOrderShowProps = {
    order: ProductionOrder;
    rawMaterials: RawMaterialOption[];
    availableVariants: VariantOption[];
};

export type ProductionOrderIngredientFormRow = {
    id: number;
    raw_material_name?: string | null;
    planned_quantity: FormNumberValue;
    actual_quantity: FormNumberValue;
    unit_cost: number;
    total_cost: number;
};

export type ProductionOrderPackagingFormRow = {
    id: number;
    presentation: string;
    presentation_value: number;
    planned_units: FormNumberValue;
    actual_units: FormNumberValue;
    cost_price: number | null;
};

export type ProductionOrderFormData = {
    actual_yield_quantity: FormNumberValue;
    viscosity_ku: FormNumberValue;
    grinding_hg: FormNumberValue;
    quality_solids: FormNumberValue;
    agitation_start_time: string;
    agitation_end_time: string;
    packaging_start_time: string;
    packaging_end_time: string;
    responsible_name: string;
    spillage_quantity: FormNumberValue;
    notes: string;
    ingredients: ProductionOrderIngredientFormRow[];
    packaging: ProductionOrderPackagingFormRow[];
    line_adjustments: never[];
};

export type ProductionOrderSetData = SetDataAction<ProductionOrderFormData>;

export type ProductionOrderErrors = FormDataErrors<ProductionOrderFormData>;

export type PreviewCostData = {
    ingredients: Array<{
        id: number;
        unit_cost: number;
        total_cost: number;
        actual_quantity: number;
    }>;
    packaging: Array<{
        id: number;
        cost_price: number;
        total_cost: number;
        equivalent: number;
        actual_units: number;
    }>;
    total_bulk_cost: number;
    total_finished_cost: number;
    total_equivalent: number;
};
