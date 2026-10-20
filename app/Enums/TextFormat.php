<?php

declare(strict_types=1);

namespace App\Enums;

use App\Casts\NormalizedText;
use Closure;
use Illuminate\Support\Str;
use Normalizer;

/**
 * Forma en que se guarda un texto que identifica un registro: un código o un nombre único (auditoría 3.6, PR 8). Dos
 * textos que el usuario lee como el mismo («rm-100» y «RM-100», «Planta  Cali» y «Planta Cali») se guardan igual, así
 * que chocan con la regla de unicidad y con el índice de la base.
 *
 * Es la única fuente de esa limpieza: la aplican los requests antes de validar (`NormalizesText`) y los modelos al
 * guardar (`NormalizedText`), para que los seeders y la carga de datos también queden limpios.
 */
enum TextFormat: string
{
    /** Códigos de materia prima, de presentación y de categoría de MP: mayúsculas y pocos símbolos (D-RM1). */
    case Code = 'code';

    /** Nombre del producto: su único identificador. Mayúsculas y los símbolos que usan los nombres reales (D-PRD2). */
    case ProductName = 'product_name';

    /** Nombres que conservan las mayúsculas como se escriben (bodegas, catálogos, roles) y el NIT. */
    case Name = 'name';

    /**
     * Tildes en una sola forma (una «É» copiada de un PDF puede llegar como «E» más un acento suelto), espacios de los
     * extremos fuera y los internos colapsados (también tabs y espacios duros) y, según el formato, mayúsculas
     * (`mb_strtoupper`, así «ñ» pasa a «Ñ»). Lo que no es texto llega tal cual: lo rechaza la regla `string`.
     */
    public function normalize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $text = Str::squish(Normalizer::normalize($value, Normalizer::FORM_C) ?: $value);

        return $this === self::Name ? $text : mb_strtoupper($text);
    }

    /**
     * Regla de caracteres del formato, con su mensaje. Va después de `string` y de `max`.
     *
     * @return list<Closure>
     */
    public function rules(): array
    {
        $pattern = $this->pattern();

        if ($pattern === null) {
            return [];
        }

        return [
            function (string $attribute, mixed $value, Closure $fail) use ($pattern): void {
                if (is_string($value) && preg_match($pattern, $value) !== 1) {
                    $fail($this->invalidMessage());
                }
            },
        ];
    }

    /** El cast del modelo para una columna de este formato. */
    public function cast(): string
    {
        return NormalizedText::class.':'.$this->value;
    }

    private function pattern(): ?string
    {
        return match ($this) {
            self::Code => '/^[\p{Lu}0-9][\p{Lu}0-9 ._\/-]*$/u',
            self::ProductName => '/^[\p{Lu}0-9][\p{Lu}0-9 .,\/%+()&°-]*$/u',
            self::Name => null,
        };
    }

    private function invalidMessage(): string
    {
        return match ($this) {
            self::Code => 'El campo :attribute solo admite letras, números, espacios y los signos . - _ /, y debe empezar por una letra o un número.',
            self::ProductName => 'El campo :attribute solo admite letras, números, espacios y los signos . , - / % + ( ) & °, y debe empezar por una letra o un número.',
            self::Name => '',
        };
    }
}
