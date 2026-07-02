<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConsumeRemnantRequest extends FormRequest
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
            'remnant_id' => ['required', 'integer', 'exists:production_remnants,id'],
            'quantity_gallons' => ['required', 'numeric', 'min:0.0001'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
