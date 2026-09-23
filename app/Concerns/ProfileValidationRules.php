<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
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
            'phone' => ['nullable', 'string', 'max:15'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'signature' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:1024', 'dimensions:max_width=4000,max_height=4000'],
            'remove_signature' => ['nullable', 'boolean'],
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
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
