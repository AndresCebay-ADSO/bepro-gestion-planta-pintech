<?php

declare(strict_types=1);

namespace App\Http\Requests\Clients;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
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
            'nit' => ['nullable', 'string', 'max:20', Rule::unique('clients', 'nit')->ignore($clientId)],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'shipping_address' => ['nullable', 'string', 'max:500'],
            // Solo con clients.deactivate (lo comprueba ClientController::update).
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
