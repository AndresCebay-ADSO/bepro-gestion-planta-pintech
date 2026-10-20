<?php

declare(strict_types=1);

namespace App\Casts;

use App\Enums\TextFormat;
use Illuminate\Contracts\Database\Eloquent\CastsInboundAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Guarda un código o un nombre en la forma de su `TextFormat` aunque no venga de un formulario (seeders, carga de
 * datos, tinker). Los requests ya lo envían normalizado; aquí no cambia nada en ese caso. Se declara con
 * `TextFormat::X->cast()`. Solo actúa al guardar: lo leído de la base ya está normalizado.
 *
 * Ojo: una búsqueda (`where`, `firstOrCreate`) no pasa por el cast. Quien busque por estas columnas debe usar el valor
 * ya normalizado (`TextFormat::X->normalize()`), o no encontrará el registro y chocará con el índice único al crearlo.
 */
class NormalizedText implements CastsInboundAttributes
{
    private readonly TextFormat $format;

    public function __construct(string $format)
    {
        $this->format = TextFormat::from($format);
    }

    /** Un texto que queda vacío se guarda como null: dos NIT vacíos no deben chocar con el índice único. */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = $this->format->normalize((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
