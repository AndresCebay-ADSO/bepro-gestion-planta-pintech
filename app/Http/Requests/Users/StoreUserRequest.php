<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:255'],
            'email' => ['bail', 'required', 'string', 'email', 'max:255', Rule::unique('users')],
            'phone' => ['bail', 'nullable', 'string', 'max:15'],
            'job_title' => ['bail', 'nullable', 'string', 'max:255'],
            'password' => ['bail', 'required', 'string', Password::default(), 'confirmed'],
            'role' => ['bail', 'required', 'string', Rule::exists('roles', 'name')],
            'is_active' => ['bail', 'required', 'boolean'],
        ];
    }
}
