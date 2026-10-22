<?php

declare(strict_types=1);

namespace App\Http\Requests\Clients;

use App\Enums\TextFormat;
use App\Http\Requests\Concerns\ClientContactRules;
use App\Http\Requests\Concerns\NormalizesText;
use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    use ClientContactRules;
    use NormalizesText;

    public function authorize(): bool
    {
        $client = $this->route('client');

        return $client instanceof Client
            && ($this->user()?->can('update', $client) ?? false);
    }

    public function rules(): array
    {
        $client = $this->route('client');
        $clientId = is_object($client) ? $client->id : $client;

        return [
            'business_name' => ['required', 'string', 'max:255'],
            'nit' => [...$this->nitRules(), Rule::unique('clients', 'nit')->ignore($clientId)],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => $this->phoneRules(),
            'shipping_address' => ['nullable', 'string', 'max:'.Client::SHIPPING_ADDRESS_MAX_LENGTH],
            // Solo con clients.deactivate (lo comprueba ClientController::update).
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** Como al crear: el NIT solo con los espacios colapsados (P8-8). */
    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizedText(['nit' => TextFormat::Name]));
    }
}
