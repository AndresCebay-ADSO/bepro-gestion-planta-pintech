import type { RawMaterialType } from '@/types';

/** Qué hace el sistema con las materias primas de cada tipo de insumo (App\Enums\RawMaterialType). */
export const RAW_MATERIAL_TYPE_HINTS: Record<RawMaterialType, string> = {
    chemical: 'Se ofrece como ingrediente de las fórmulas.',
    container: 'Se ofrece como envase de las presentaciones.',
    label: 'Se ofrece como etiqueta de las presentaciones.',
    secondary_packaging: 'Se usa en el termoencogido (bandejas, bolsas).',
};
