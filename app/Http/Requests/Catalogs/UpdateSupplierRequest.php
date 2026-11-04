<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs;

use App\Models\Supplier;

class UpdateSupplierRequest extends StoreSupplierRequest
{
    public function authorize(): bool
    {
        $supplier = $this->currentSupplier();

        return $supplier !== null && ($this->user()?->can('update', $supplier) ?? false);
    }

    protected function currentSupplier(): ?Supplier
    {
        $supplier = $this->route('supplier');

        return $supplier instanceof Supplier ? $supplier : null;
    }
}
