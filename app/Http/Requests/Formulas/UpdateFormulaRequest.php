<?php

declare(strict_types=1);

namespace App\Http\Requests\Formulas;

use App\Models\Formula;

class UpdateFormulaRequest extends StoreFormulaRequest
{
    /** Heredaba el de crear: editar pedía el permiso de crear fórmulas. */
    public function authorize(): bool
    {
        $formula = $this->route('formula');

        return $formula instanceof Formula && ($this->user()?->can('update', $formula) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Sin is_active: activar o desactivar va por sus propias acciones, con sus reglas (P9-3).
        return $this->commonRules();
    }
}
