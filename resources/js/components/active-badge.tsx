import { pillClasses, toneClasses } from '@/lib/tones';
import { cn } from '@/lib/utils';

type ActiveBadgeProps = {
    isActive: boolean;
    /** Para sustantivos femeninos (bodega, fórmula, materia prima, presentación, categoría, unidad): «Activa». */
    feminine?: boolean;
    className?: string;
};

/**
 * Estado de un registro maestro (`is_active`): «Activo» en verde, «Inactivo» en gris (DESIGN.md §4); «Activa» e
 * «Inactiva» con `feminine`. Igual en todos los
 * módulos; no es un enum, por eso no pasa por la prop `enums`.
 *
 *     <ActiveBadge isActive={client.is_active} />
 */
export function ActiveBadge({
    isActive,
    feminine = false,
    className,
}: ActiveBadgeProps) {
    return (
        <span
            className={cn(
                pillClasses,
                toneClasses[isActive ? 'success' : 'neutral'],
                className,
            )}
        >
            {isActive
                ? feminine
                    ? 'Activa'
                    : 'Activo'
                : feminine
                  ? 'Inactiva'
                  : 'Inactivo'}
        </span>
    );
}
