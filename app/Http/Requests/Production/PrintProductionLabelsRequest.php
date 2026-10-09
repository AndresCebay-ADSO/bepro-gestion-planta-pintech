<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PrintProductionLabelsRequest extends FormRequest
{
    /**
     * Permiso y estado de la orden (cualquiera menos cancelada) en ProductionOrderPolicy::printLabels.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('printLabels', $this->route('production_order')) ?? false;
    }

    /**
     * Sin parámetros: el PDF es una sola estampita y las copias se eligen en el diálogo de impresión del navegador.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
