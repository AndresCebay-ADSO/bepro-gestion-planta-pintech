<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\RawMaterialType;
use App\Models\RawMaterial;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * La materia prima elegida debe ser del tipo de insumo esperado (tipo de su categoría). Para Químico vale también una
 * materia prima sin categoría, igual que en RawMaterial::scopeUsableInFormulas().
 */
final class RawMaterialOfType implements ValidationRule
{
    public function __construct(private readonly RawMaterialType $type) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $query = RawMaterial::query()->whereKey((int) $value);
        $matches = $this->type === RawMaterialType::Chemical
            ? $query->usableInFormulas()->exists()
            : $query->ofType($this->type)->exists();

        if (! $matches) {
            $fail(__('La materia prima elegida no es de tipo :type.', ['type' => mb_strtolower($this->type->label())]));
        }
    }
}
