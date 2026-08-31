<?php

declare(strict_types=1);

namespace App\Http\Requests\RawMaterials;

use App\Models\RawMaterial;
use Illuminate\Foundation\Http\FormRequest;

class IndexRawMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', RawMaterial::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,inactive,all'],
        ];
    }
}
