import { usePage } from '@inertiajs/react';

import type { EnumCase, ToneEnumName } from '@/types/enums';

/**
 * Etiqueta y tono de un caso de enum, desde la prop compartida `enums` (`App\Support\ToneEnums`). Sirve para mostrar la
 * etiqueta sin pastilla (textos, selects); para la pastilla, usar `<StatusBadge>`. `undefined` si el valor no existe
 * en el enum o no hay sesión.
 */
export function useEnumCase(
    enumName: ToneEnumName,
    value: string | null | undefined,
): EnumCase | undefined {
    const { enums } = usePage().props;

    if (value === null || value === undefined) {
        return undefined;
    }

    return enums?.[enumName]?.[value];
}
