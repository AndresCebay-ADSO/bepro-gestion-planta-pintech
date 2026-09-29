<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipo de insumo de una categoría de materia prima: le dice al sistema dónde se usa cada materia prima. Las categorías
 * (Resinas, Solventes, Envases metálicos, Bandejas…) las crea el usuario; el tipo es fijo porque de él depende qué ofrece
 * cada selector. Sustituye a reconocer los envases por el nombre de su categoría.
 */
enum RawMaterialType: string
{
    /** Entra en las fórmulas. */
    case Chemical = 'chemical';

    /** Envase de una presentación. */
    case Container = 'container';

    /** Etiqueta que se pega al envase. */
    case Label = 'label';

    /** Bandejas, bolsas y demás material del termoencogido. */
    case SecondaryPackaging = 'secondary_packaging';

    public function label(): string
    {
        return match ($this) {
            self::Chemical => __('Químico'),
            self::Container => __('Envase'),
            self::Label => __('Etiqueta'),
            self::SecondaryPackaging => __('Empaque secundario'),
        };
    }
}
