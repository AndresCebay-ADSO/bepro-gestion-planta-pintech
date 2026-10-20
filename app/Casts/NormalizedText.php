<?php

declare(strict_types=1);

namespace App\Casts;

use App\Enums\TextFormat;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Guarda un código o un nombre en la forma de su `TextFormat` aunque no venga de un formulario (seeders, carga de
 * datos, tinker). Los requests ya lo envían normalizado; aquí no cambia nada en ese caso. Se declara con
 * `TextFormat::X->cast()`.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class NormalizedText implements CastsAttributes
{
    private readonly TextFormat $format;

    public function __construct(string $format)
    {
        $this->format = TextFormat::from($format);
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : $this->format->normalize((string) $value);
    }
}
