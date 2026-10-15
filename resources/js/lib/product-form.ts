import type { DecimalLimits } from '@/lib/numeric-input';

// Los topes de cada columna: los rangos de calidad son numeric(4,1); CIF y umbral, numeric(5,2).
const QUALITY_RANGE: DecimalLimits = { maxDecimals: 1, maxIntegerDigits: 3 };
const PERCENTAGE: DecimalLimits = { maxDecimals: 2, maxIntegerDigits: 3 };

/** Campos decimales del formulario de producto (crear y editar), con los topes que se pasan a `<DecimalInput>`. */
export const PRODUCT_DECIMAL_LIMITS = {
    cif_percentage: PERCENTAGE,
    price_threshold: PERCENTAGE,
    quality_viscosity_lower: QUALITY_RANGE,
    quality_viscosity_upper: QUALITY_RANGE,
    quality_fineness_lower: QUALITY_RANGE,
    quality_fineness_upper: QUALITY_RANGE,
    quality_solids_lower: QUALITY_RANGE,
    quality_solids_upper: QUALITY_RANGE,
} satisfies Record<string, DecimalLimits>;

/** Los mismos campos: se convierten al enviar con `withDecimalPayloads`. */
export const PRODUCT_DECIMAL_FIELDS = Object.keys(
    PRODUCT_DECIMAL_LIMITS,
) as (keyof typeof PRODUCT_DECIMAL_LIMITS)[];
