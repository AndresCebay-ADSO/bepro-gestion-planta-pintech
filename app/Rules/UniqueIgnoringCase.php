<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Como `Rule::unique`, pero sin distinguir mayúsculas: con «Esmaltes» creada, «esmaltes» también está tomado.
 * `Rule::unique` y los índices únicos comparan con `=`, que distingue mayúsculas en PostgreSQL y en SQLite.
 *
 * El `LOWER()` de SQLite solo cambia las letras sin tilde: «ÉPOXICOS» contra «époxicos» se detecta en PostgreSQL,
 * no en las pruebas en memoria.
 */
final class UniqueIgnoringCase implements ValidationRule
{
    public function __construct(
        private readonly string $table,
        private readonly string $column,
        private readonly ?int $ignoreId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $query = DB::table($this->table);
        $column = $query->getGrammar()->wrap($this->column);

        $taken = $query
            ->whereRaw("LOWER({$column}) = ?", [mb_strtolower($value)])
            ->when($this->ignoreId !== null, fn ($query) => $query->where('id', '!=', $this->ignoreId))
            ->exists();

        if ($taken) {
            $fail('validation.unique')->translate();
        }
    }
}
