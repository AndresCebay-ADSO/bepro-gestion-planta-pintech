<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs;

use App\Models\ShrinkWrapType;

class UpdateShrinkWrapTypeRequest extends StoreShrinkWrapTypeRequest
{
    public function authorize(): bool
    {
        $type = $this->currentType();

        return $type !== null && ($this->user()?->can('update', $type) ?? false);
    }

    protected function currentType(): ?ShrinkWrapType
    {
        $type = $this->route('shrink_wrap_type');

        return $type instanceof ShrinkWrapType ? $type : null;
    }
}
