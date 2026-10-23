import { useEnumCase } from '@/hooks/use-enum-case';
import type { ToneEnumName } from '@/types/enums';

type EnumLabelProps = {
    /** Nombre corto del enum en PHP, tal como lo registra `App\Support\ToneEnums::ALL`. */
    enum: ToneEnumName;
    value: string | null | undefined;
};

/**
 * Etiqueta de un caso de enum como texto, sin pastilla (por ejemplo, el tipo de bodega dentro de una celda). Sirve
 * dentro de listas, donde no se puede llamar a `useEnumCase` en cada vuelta del `map`. Un valor desconocido se muestra
 * tal cual, como en `<StatusBadge>`.
 */
export function EnumLabel({ enum: enumName, value }: EnumLabelProps) {
    const enumCase = useEnumCase(enumName, value);

    return <>{enumCase?.label ?? value ?? ''}</>;
}
