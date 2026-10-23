import { SignalHigh, SignalLow, SignalMedium } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

import { useEnumCase } from '@/hooks/use-enum-case';
import { pillClasses, toneClasses } from '@/lib/tones';
import { cn } from '@/lib/utils';

type PriorityBadgeProps = {
    /** Valor de `SalesOrderPriority` (`low`, `medium`, `high`). */
    value: string;
    className?: string;
};

/*
 * Excepción documentada a «sin mapas en las páginas»: las barras son forma, no color, y no tienen equivalente en PHP.
 * El tono sigue saliendo del enum (solo `high` lleva color).
 */
const bars: Record<string, LucideIcon> = {
    low: SignalLow,
    medium: SignalMedium,
    high: SignalHigh,
};

/**
 * Prioridad de un pedido (DESIGN.md §4): icono de barras (3 alta, 2 media, 1 baja) y etiqueta. Solo la alta tiene
 * color; el icono comunica el nivel sin depender del color.
 *
 *     <PriorityBadge value={order.priority} />
 */
export function PriorityBadge({ value, className }: PriorityBadgeProps) {
    const enumCase = useEnumCase('SalesOrderPriority', value);
    const Icon = bars[value] ?? SignalLow;

    return (
        <span
            className={cn(
                pillClasses,
                toneClasses[enumCase?.tone ?? 'neutral'],
                className,
            )}
        >
            <Icon className="size-4" aria-hidden="true" />
            {enumCase?.label ?? value}
        </span>
    );
}
