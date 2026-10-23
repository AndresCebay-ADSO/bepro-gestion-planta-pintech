import { useEnumCase } from '@/hooks/use-enum-case';
import { pillClasses, toneClasses } from '@/lib/tones';
import { cn } from '@/lib/utils';
import type { ToneEnumName } from '@/types/enums';

type StatusBadgeProps = {
    /** Nombre corto del enum en PHP, tal como lo registra `App\Support\ToneEnums::ALL`. */
    enum: ToneEnumName;
    /** Valor del caso, el mismo que guarda la base (`order.status`). */
    value: string;
    className?: string;
};

/**
 * Pastilla de estado (DESIGN.md §4). La etiqueta y el tono salen del enum en PHP (prop compartida `enums`); la pantalla
 * solo dice qué enum y qué valor:
 *
 *     <StatusBadge enum="SalesOrderStatus" value={order.status} />
 *
 * No hay mapas de color en las páginas. Un valor que el enum no conoce se muestra tal cual, en neutro, y avisa en la
 * consola en desarrollo.
 */
export function StatusBadge({
    enum: enumName,
    value,
    className,
}: StatusBadgeProps) {
    const enumCase = useEnumCase(enumName, value);

    if (!enumCase && import.meta.env.DEV) {
        console.warn(`StatusBadge: «${value}» no está en ${enumName}.`);
    }

    return (
        <span
            className={cn(
                pillClasses,
                toneClasses[enumCase?.tone ?? 'neutral'],
                className,
            )}
        >
            {enumCase?.label ?? value}
        </span>
    );
}
