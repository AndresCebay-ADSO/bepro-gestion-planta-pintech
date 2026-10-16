import type { DecimalLimits } from '@/lib/numeric-input';

/**
 * Espejo de `App\Enums\NumberType` (auditoría 3.6, PR 4): cuántos decimales y dígitos enteros admite cada tipo de
 * columna. El servidor decide; esto solo evita que el campo deje escribir lo que la columna no puede guardar.
 *
 * Si cambia el tamaño de un tipo en el enum, cambia aquí también.
 */
export const NUMBER_TYPES = {
    /** Kilos, litros, galones: numeric(12,4). */
    quantity: { maxDecimals: 4, maxIntegerDigits: 8 },
    /** Pesos: numeric(16,4). */
    money: { maxDecimals: 4, maxIntegerDigits: 12 },
    /** Porcentajes que se digitan: numeric(5,2). */
    percentage: { maxDecimals: 2, maxIntegerDigits: 3 },
    /** Equivalencias entre unidades: numeric(14,6). */
    factor: { maxDecimals: 6, maxIntegerDigits: 8 },
    /** Densidad en kg/galón: numeric(10,4). */
    density: { maxDecimals: 4, maxIntegerDigits: 6 },
    /** KU, Hegman, % de sólidos: numeric(4,1). */
    quality: { maxDecimals: 1, maxIntegerDigits: 3 },
} satisfies Record<string, DecimalLimits>;

/** Unidades enteras (envases, etiquetas, cotización, pedido, PT): columna `integer`, hasta 2.147.483.647. */
export const UNITS_MAX_DIGITS = 10;

/** Molienda en la escala Hegman: de 0 a 8, con un decimal (el tope lo da el servidor). */
export const HEGMAN_LIMITS: DecimalLimits = {
    ...NUMBER_TYPES.quality,
    maxIntegerDigits: 1,
};
