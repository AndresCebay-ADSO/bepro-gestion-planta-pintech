<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use App\Enums\RawMaterialType;
use App\Rules\RawMaterialOfType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLineAdjustmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('updateOperationalData', $this->route('production_order')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'raw_material_id' => [
                'required',
                Rule::exists('raw_materials', 'id')->where('is_active', true),
                // Se suma al granel al completar: un envase o una etiqueta no son granel (3.7, decisión del 2026-09-30).
                new RawMaterialOfType(RawMaterialType::Chemical),
            ],
            'quantity' => 'required|numeric|min:0.0001',
            'reason' => 'required|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
