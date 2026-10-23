import { ArrowDown, ArrowLeftRight, ArrowUp } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

import { useEnumCase } from '@/hooks/use-enum-case';
import { pillClasses, toneClasses } from '@/lib/tones';
import { cn } from '@/lib/utils';

type MovementDirectionBadgeProps = {
    /** `entry` o `exit` (columna `type` de los movimientos de MP y PT), o `transfer` para un traslado de PT. */
    type: string;
    className?: string;
};

/** Icono de cada dirección (DESIGN.md §4): lo único que decide el componente. */
const icons: Record<string, LucideIcon> = {
    entry: ArrowDown,
    exit: ArrowUp,
    transfer: ArrowLeftRight,
};

/**
 * Pastilla de dirección de un movimiento de inventario: icono + etiqueta (DESIGN.md §4). El signo (+/−) va en la
 * cantidad de la fila, no aquí, para no repetirlo.
 *
 * La etiqueta y el tono salen del enum en PHP, como en `<StatusBadge>`: entrada y salida de `InventoryMovementType`, y
 * el traslado de `FinishedInventoryMovementReason` (es una razón, no un tipo). Un valor desconocido se muestra tal cual,
 * en neutro y sin icono.
 */
export function MovementDirectionBadge({
    type,
    className,
}: MovementDirectionBadgeProps) {
    const enumCase = useEnumCase(
        type === 'transfer'
            ? 'FinishedInventoryMovementReason'
            : 'InventoryMovementType',
        type,
    );
    const Icon = icons[type];
    const label = enumCase?.label ?? type;

    return (
        <span
            className={cn(
                pillClasses,
                toneClasses[enumCase?.tone ?? 'neutral'],
                className,
            )}
        >
            {Icon && <Icon className="size-3.5" aria-hidden="true" />}
            {label}
        </span>
    );
}
