import type { DecimalLimits } from '@/lib/numeric-input';

/**
 * Espejo de `App\Enums\NumberType` (auditoría 3.6, PR 4): cuántos decimales se digitan y cuántos dígitos enteros
 * admite cada tipo de columna. El servidor decide; esto solo evita que el campo deje escribir lo que la columna no puede guardar.
 *
 * Si cambia el tamaño de un tipo en el enum, cambia aquí también.
 */
export const NUMBER_TYPES = {
    /** Kilos, litros, galones: numeric(12,4). */
    quantity: { maxDecimals: 4, maxIntegerDigits: 8 },
    /** Pesos: numeric(16,4), pero se digitan con 2 decimales (`NumberType::inputScale()`). */
    money: { maxDecimals: 2, maxIntegerDigits: 12 },
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

/** Galones de una OP: hasta 10.000 (`ProductionOrder::MAX_QUANTITY`, D-OP3). El tope exacto lo da el servidor. */
export const PRODUCTION_ORDER_QUANTITY_LIMITS: DecimalLimits = {
    ...NUMBER_TYPES.quantity,
    maxIntegerDigits: 5,
};

/** Contenido de una presentación: hasta 1.000 galones, la unidad del producto (`ProductVariant::MAX_PRESENTATION_VALUE`, PRD-02). */
export const PRESENTATION_VALUE_LIMITS: DecimalLimits = {
    ...NUMBER_TYPES.quantity,
    maxIntegerDigits: 4,
};

/** Números técnicos de una solicitud de I+D: 2 decimales; los rangos físicos los da el servidor (PDR-02). */
export const TECHNICAL_NUMBER_LIMITS: DecimalLimits = {
    maxDecimals: 2,
    maxIntegerDigits: 5,
};
