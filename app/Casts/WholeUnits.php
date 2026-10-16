<?php

declare(strict_types=1);

namespace App\Casts;

use App\Enums\NumberType;
use App\Services\DecimalCalculator;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Columnas de unidades enteras (`NumberType::Units`): envases, etiquetas, unidades de PT, de cotización y de pedido.
 *
 * Los servicios suman y restan con `DecimalCalculator`, que devuelve texto con decimales ('7.0000'). PostgreSQL
 * rechaza ese texto en una columna `integer`, y el cast `integer` de Eloquent no lo convierte al guardar. Este cast lo
 * convierte en un solo lugar y se detiene si llega una fracción real, porque eso sería un error de lógica: la regla
 * del request ya exige enteros.
 *
 * @implements CastsAttributes<int|null, int|string|float|null>
 */
class WholeUnits implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        return $value === null ? null : (int) $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        $text = is_float($value) ? (string) $value : trim((string) $value);

        if (! is_numeric($text)) {
            throw new \DomainException("El valor de {$key} no es un número: {$text}.");
        }

        $calculator = app(DecimalCalculator::class);

        try {
            $normalized = $calculator->normalize($text);
        } catch (\InvalidArgumentException) {
            throw new \DomainException("{$key} está fuera del rango de unidades enteras: {$text}.");
        }

        // add(..., 0) trunca: si el valor cambia, traía una fracción real.
        $whole = $calculator->add($normalized, '0', 0);

        if ($calculator->cmp($normalized, $whole, 8) !== 0) {
            throw new \DomainException("{$key} se cuenta en unidades enteras; llegó {$text}.");
        }

        $max = NumberType::Units->max();

        if ($calculator->cmp($calculator->abs($whole, 0), $max, 0) > 0) {
            throw new \DomainException("{$key} está fuera del rango de unidades enteras: {$text}.");
        }

        return (int) $whole;
    }
}
