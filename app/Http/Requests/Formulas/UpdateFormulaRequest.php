<?php

declare(strict_types=1);

namespace App\Http\Requests\Formulas;

class UpdateFormulaRequest extends StoreFormulaRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(
            [
                'is_active' => ['required', 'boolean'],
            ],
            $this->commonRules()
        );
    }
}
