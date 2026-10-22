<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Http\Requests\Concerns\ClientContactRules;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    use ClientContactRules;

    /**
     * Prepare the data for validation when used within a FormRequest.
     */
    protected function prepareForValidation(): void
    {
        if (method_exists($this, 'has') && method_exists($this, 'merge') && $this->has('email') && is_string($this->input('email'))) {
            $this->merge([
                'email' => Str::lower(trim((string) $this->input('email'))),
            ]);
        }
    }

    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
            'phone' => $this->phoneRules(),
            'job_title' => ['nullable', 'string', 'max:255'],
            // `bail`: un archivo que no es una imagen recibe un solo mensaje, no tres.
            'signature' => ['nullable', 'bail', 'image', 'mimes:png,jpg,jpeg', 'max:1024', 'dimensions:max_width=4000,max_height=4000'],
            'remove_signature' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Mensajes de la firma, iguales en el perfil y en Usuarios: dicen qué archivo se espera, no solo «dimensiones no válidas».
     *
     * @return array<string, string>
     */
    protected function signatureMessages(): array
    {
        return [
            'signature.image' => 'La firma debe ser una imagen PNG o JPG.',
            'signature.mimes' => 'La firma debe ser una imagen PNG o JPG.',
            'signature.dimensions' => 'La firma debe medir como máximo 4000 × 4000 píxeles.',
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'lowercase',
            'email',
            // `email` acepta `nuevo@pintech`: sirve para entrar, pero el correo de recuperación nunca llegaría (USR-03).
            'regex:/@[^@\s]+\.[^@\s]+$/',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
