<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Concerns\ProfileValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->profileRules(), [
            'password' => ['bail', 'required', 'string', Password::default(), 'confirmed'],
            'role' => ['bail', 'required', 'string', Rule::exists('roles', 'name')],
            'is_active' => ['bail', 'required', 'boolean'],
        ]);
    }
}
