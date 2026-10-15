/** Campos decimales del formulario de producto (crear y editar): se convierten al enviar con `withDecimalPayloads`. */
export const PRODUCT_DECIMAL_FIELDS = [
    'cif_percentage',
    'price_threshold',
    'quality_viscosity_lower',
    'quality_viscosity_upper',
    'quality_fineness_lower',
    'quality_fineness_upper',
    'quality_solids_lower',
    'quality_solids_upper',
] as const;
