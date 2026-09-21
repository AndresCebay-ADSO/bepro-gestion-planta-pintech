<?php

declare(strict_types=1);

namespace App\Http\Requests\Clients;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;

class IndexClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Client::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,inactive,all'],
        ];
    }
}
