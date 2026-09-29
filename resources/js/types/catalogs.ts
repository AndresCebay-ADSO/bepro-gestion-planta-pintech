/** Cuántos registros usan una unidad de medida (UnitOfMeasure::USAGE_RELATIONS). */
export type UnitOfMeasureUsage = {
    raw_materials: number;
    products: number;
    product_variants: number;
    formula_details: number;
};

export type UnitOfMeasureRow = {
    id: number;
    code: string;
    name: string;
    symbol: string;
    to_kg_conversion: string | null;
    to_liter_conversion: string | null;
    is_active: boolean;
    usage: UnitOfMeasureUsage;
    in_use: boolean;
    /** La usan líneas de fórmula o materias primas: cambiar su equivalencia pide confirmación. */
    affects_conversions: boolean;
};

export type UnitOfMeasureFormData = {
    code: string;
    name: string;
    symbol: string;
    description: string;
    to_kg_conversion: string;
    to_liter_conversion: string;
    is_active: boolean;
    confirm_factor_change: boolean;
};

/** Tipo de insumo de una categoría de materia prima (App\Enums\RawMaterialType). */
export type RawMaterialType =
    'chemical' | 'container' | 'label' | 'secondary_packaging';

export type RawMaterialCategoryRow = {
    id: number;
    code: string;
    name: string;
    type: RawMaterialType;
    type_label: string;
    is_active: boolean;
    raw_materials_count: number;
};

export type RawMaterialCategoryFormData = {
    code: string;
    name: string;
    description: string;
    type: RawMaterialType;
    is_active: boolean;
};

export type ProductCategoryRow = {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    products_count: number;
};

export type ProductCategoryFormData = {
    name: string;
    description: string;
    is_active: boolean;
};
