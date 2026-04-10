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

import { formatNumber, formatCurrency, formatQuantity, formatPercent } from '@/lib/formatters';
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
    /** Valor a mostrar cuando el número es null/undefined (default: '-') */
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

export function FormattedNumber({
    value,
    className,
    currency,
    percent,
    maxDecimals = 4,
    emptyValue = '-',
    align = 'left',
    colorize = false,
    size = 'base',
    bold = false,
}: FormattedNumberProps) {
    // Determinar la función de formateo
    let formatted: string;

    if (percent) {
        formatted = formatPercent(value, maxDecimals);
    } else if (currency) {
        const currencySymbol = typeof currency === 'string' ? currency : '$';
        formatted = formatCurrency(value, currencySymbol, { maxDecimals, emptyValue });
    } else {
        formatted = formatQuantity(value, { maxDecimals, emptyValue });
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
        colorize && numericValue !== null && numericValue !== undefined && !Number.isNaN(numericValue)
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
            )}
        >
            {formatted}
        </span>
    );
}

export { formatNumber, formatCurrency, formatQuantity, formatPercent };
