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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Una página por estampita, y DomPDF crece más que lineal: 200 tardan ~2,4 s y ~100 MB; 300 ya pasan los 128 MB
            // que PHP trae por defecto. Más de 200 se imprimen en dos tandas (la DYMO tarda minutos en sacar 200).
            'quantity' => ['required', 'integer', 'min:1', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'quantity' => 'cantidad',
        ];
    }
}
