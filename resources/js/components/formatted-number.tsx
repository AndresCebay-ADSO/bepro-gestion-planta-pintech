/**
 * Componente FormattedNumber
 *
 * Muestra números formateados según el estándar colombiano.
 * Este componente es de solo lectura y no debe usarse en inputs de edición.
 *
 * Uso:
 * ```tsx
 * <FormattedNumber value={1500.25} currency />           // $1.500,25
 * <FormattedNumber value={5432.0000} />                     // 5.432
 * <FormattedNumber value={12.5432} maxDecimals={2} />      // 12,54
 * <FormattedNumber value={null} emptyValue="N/A" />        // N/A
 * ```
 */

import { EmptyCell } from '@/components/empty-cell';
import type { NumberKind } from '@/lib/formatters';
import {
    formatNumber,
    formatCurrency,
    formatQuantity,
    formatPercent,
    formatKind,
    isEmptyNumber,
    NUMBER_KINDS,
} from '@/lib/formatters';
import { cn } from '@/lib/utils';

interface FormattedNumberBaseProps {
    /** Valor a formatear */
    value: number | string | null | undefined;
    /** Clases CSS adicionales */
    className?: string;
    /** Valor a mostrar cuando el número es null/undefined, atenuado (por defecto pinta <EmptyCell />). Ej.: «Sin precio» */
    emptyValue?: string;
    /** Alineación del texto */
    align?: 'left' | 'right' | 'center';
    /** Color basado en el valor (positivo/negativo) */
    colorize?: boolean;
    /** Tamaño de fuente */
    size?: 'sm' | 'base' | 'lg';
    /** Mostrar el valor en negrita */
    bold?: boolean;
}

/** Con `kind`, el tipo decide moneda, decimales y ceros finales (DESIGN.md §7): no se mezcla con las opciones sueltas. */
interface FormattedNumberKindProps {
    /** Tipo predefinido según estándar de diseño (quantity, units, clientPrice, cost, unitCost, percent) */
    kind: NumberKind;
    currency?: never;
    percent?: never;
    maxDecimals?: never;
    trimTrailingZeros?: never;
}

/** Sin `kind`: lo que no tiene tipo, como las mediciones con los decimales de su columna. */
interface FormattedNumberManualProps {
    kind?: undefined;
    /** Mostrar como moneda (agrega símbolo $ por defecto) */
    currency?: boolean | string;
    /** Mostrar como porcentaje (puntos porcentuales: 19 → «19%») */
    percent?: boolean;
    /** Máximo de decimales a mostrar (default: 4) */
    maxDecimals?: number;
    /** Eliminar ceros al final de decimales (útil para vistas de detalle) */
    trimTrailingZeros?: boolean;
}

type FormattedNumberProps = FormattedNumberBaseProps &
    (FormattedNumberKindProps | FormattedNumberManualProps);

export function FormattedNumber({
    value,
    kind,
    className,
    currency,
    percent,
    maxDecimals = 4,
    emptyValue,
    align = 'left',
    colorize = false,
    size = 'base',
    bold = false,
    trimTrailingZeros = false,
}: FormattedNumberProps) {
    // Sin número: sin emptyValue pinta <EmptyCell />; con emptyValue, texto atenuado (DESIGN.md §9)
    const isEmpty = isEmptyNumber(value);
    let formatted = '';

    if (!isEmpty) {
        if (kind) {
            formatted = formatKind(value, kind, emptyValue);
        } else if (percent) {
            formatted = formatPercent(
                value,
                maxDecimals,
                emptyValue,
                trimTrailingZeros,
            );
        } else if (currency) {
            const currencySymbol =
                typeof currency === 'string' ? currency : '$';
            formatted = formatCurrency(value, currencySymbol, {
                maxDecimals,
                emptyValue,
                trimTrailingZeros,
            });
        } else {
            formatted = formatQuantity(value, {
                maxDecimals,
                emptyValue,
                trimTrailingZeros,
            });
        }
    }

    // Determinar clases de alineación
    const alignClasses = {
        left: 'text-left',
        right: 'text-right',
        center: 'text-center',
    };

    // Determinar clases de tamaño
    const sizeClasses = {
        sm: 'text-xs',
        base: 'text-sm',
        lg: 'text-base',
    };

    // Determinar color basado en el valor si colorize está activado
    const numericValue = typeof value === 'string' ? parseFloat(value) : value;
    const colorClass =
        colorize &&
        !isEmpty &&
        numericValue !== null &&
        numericValue !== undefined &&
        !Number.isNaN(numericValue)
            ? numericValue < 0
                ? 'text-destructive-text'
                : numericValue > 0
                  ? 'text-success-text'
                  : ''
            : '';

    return (
        <span
            className={cn(
                'inline-block tabular-nums',
                alignClasses[align],
                sizeClasses[size],
                colorClass,
                bold && 'font-semibold',
                className,
                isEmpty && emptyValue !== undefined && 'text-muted-foreground',
            )}
        >
            {isEmpty ? (emptyValue ?? <EmptyCell />) : formatted}
        </span>
    );
}

export type { NumberKind };
export {
    formatNumber,
    formatCurrency,
    formatQuantity,
    formatPercent,
    formatKind,
    isEmptyNumber,
    NUMBER_KINDS,
};
