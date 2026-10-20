<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\TextFormat;

/**
 * Normaliza códigos y nombres antes de validar, para que la unicidad y la regla de caracteres vean el valor que se va a
 * guardar (auditoría 3.6, PR 8).
 */
trait NormalizesText
{
    /**
     * Los campos enviados como texto, ya normalizados, para sumarlos al `merge` de `prepareForValidation`.
     *
     * @param  array<string, TextFormat>  $formats
     * @return array<string, mixed>
     */
    protected function normalizedText(array $formats): array
    {
        $normalized = [];

        foreach ($formats as $key => $format) {
            if (is_string($this->input($key))) {
                $normalized[$key] = $format->normalize($this->input($key));
            }
        }

        return $normalized;
    }
}
