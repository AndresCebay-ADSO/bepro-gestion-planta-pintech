import { HEGMAN_LIMITS, NUMBER_TYPES } from '@/lib/number-types';
import type { DecimalLimits } from '@/lib/numeric-input';

// Los topes de cada columna (NumberType): los rangos de calidad son numeric(4,1), con la molienda en la escala Hegman
// (0 a 8); CIF y umbral, numeric(5,2). El CIF es un recargo y puede pasar de 100 %.
const QUALITY_RANGE: DecimalLimits = NUMBER_TYPES.quality;
const PERCENTAGE: DecimalLimits = NUMBER_TYPES.percentage;

/** Campos decimales del formulario de producto (crear y editar), con los topes que se pasan a `<DecimalInput>`. */
export const PRODUCT_DECIMAL_LIMITS = {
    cif_percentage: PERCENTAGE,
    price_threshold: PERCENTAGE,
    quality_viscosity_lower: QUALITY_RANGE,
    quality_viscosity_upper: QUALITY_RANGE,
    quality_fineness_lower: HEGMAN_LIMITS,
    quality_fineness_upper: HEGMAN_LIMITS,
    quality_solids_lower: QUALITY_RANGE,
    quality_solids_upper: QUALITY_RANGE,
} satisfies Record<string, DecimalLimits>;

/** Los mismos campos: se convierten al enviar con `withDecimalPayloads`. */
export const PRODUCT_DECIMAL_FIELDS = Object.keys(
    PRODUCT_DECIMAL_LIMITS,
) as (keyof typeof PRODUCT_DECIMAL_LIMITS)[];
