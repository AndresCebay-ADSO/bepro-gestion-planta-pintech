<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotations;

use App\Models\Quotation;

class UpdateQuotationRequest extends StoreQuotationRequest
{
    public function authorize(): bool
    {
        $quotation = $this->route('quotation');

        return $quotation !== null
            && $this->user()?->can('update', $quotation);
    }

    /**
     * @return array{client_id: int|null, product_ids: array<int, int>, variant_ids: array<int, int>}
     */
    protected function keptRecords(): array
    {
        $quotation = $this->route('quotation');

        return $quotation instanceof Quotation
            ? $quotation->keptRecords()
            : parent::keptRecords();
    }
}
