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
import {
    formatNumber,
    formatCurrency,
    formatQuantity,
    formatPercent,
    isEmptyNumber,
} from '@/lib/formatters';
import { cn } from '@/lib/utils';

interface FormattedNumberProps {
    /** Valor a formatear */
    value: number | string | null | undefined;
    /** Clases CSS adicionales */
    className?: string;
    /** Mostrar como moneda (agrega símbolo $ por defecto) */
    currency?: boolean | string;
    /** Mostrar como porcentaje (multiplica por 100) */
    percent?: boolean;
    /** Máximo de decimales a mostrar (default: 4) */
    maxDecimals?: number;
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
    /** Eliminar ceros al final de decimales (útil para vistas de detalle) */
    trimTrailingZeros?: boolean;
}

export function FormattedNumber({
    value,
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
        if (percent) {
            formatted = formatPercent(value, maxDecimals, emptyValue);
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
                ? 'text-red-600 dark:text-red-400'
                : numericValue > 0
                  ? 'text-emerald-600 dark:text-emerald-400'
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

export {
    formatNumber,
    formatCurrency,
    formatQuantity,
    formatPercent,
    isEmptyNumber,
};
