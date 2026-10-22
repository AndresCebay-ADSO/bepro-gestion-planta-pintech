<?php

declare(strict_types=1);

namespace App\Http\Requests\Clients;

use App\Enums\TextFormat;
use App\Http\Requests\Concerns\ClientContactRules;
use App\Http\Requests\Concerns\NormalizesText;
use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    use ClientContactRules;
    use NormalizesText;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Client::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'nit' => [...$this->nitRules(), 'unique:clients,nit'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => $this->phoneRules(),
            'shipping_address' => ['nullable', 'string', 'max:'.Client::SHIPPING_ADDRESS_MAX_LENGTH],
        ];
    }

    /**
     * El NIT se guarda como texto (P8-8): solo se colapsan los espacios. Con puntos o sin ellos son dos NIT distintos
     * (CL-06).
     */
    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizedText(['nit' => TextFormat::Name]));
    }
}
