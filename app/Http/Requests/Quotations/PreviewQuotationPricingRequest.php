<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotations;

use App\Models\Quotation;

/**
 * Vista previa de precios del formulario de cotización (PR 4b de la auditoría 3.6). Valida las líneas y el IVA con las
 * mismas reglas que guardar; al editar, acepta las presentaciones que la cotización ya tenía aunque estén inactivas.
 */
class PreviewQuotationPricingRequest extends UpdateQuotationRequest
{
    public function authorize(): bool
    {
        return $this->route('quotation') instanceof Quotation
            ? parent::authorize()
            : ($this->user()?->can('create', Quotation::class) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_filter(
            $this->sharedRules(),
            fn (string $field): bool => $field === 'iva_percentage' || str_starts_with($field, 'items'),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
