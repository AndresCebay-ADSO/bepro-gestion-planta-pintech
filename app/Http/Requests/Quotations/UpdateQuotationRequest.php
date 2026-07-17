<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotations;

class UpdateQuotationRequest extends StoreQuotationRequest
{
    public function authorize(): bool
    {
        $quotation = $this->route('quotation');

        return $quotation !== null
            && $this->user()?->can('update', $quotation);
    }
}
