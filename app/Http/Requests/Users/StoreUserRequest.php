<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Services\AssignableRoleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->profileRules(), [
            'password' => ['bail', 'required', 'string', Password::default(), 'confirmed'],
            // Sin escalada de privilegios: solo roles cuyos permisos ya tiene quien asigna (super-admin, solo un SuperAdmin).
            'role' => ['bail', 'required', 'string', Rule::in($this->assignableRoleNames())],
            'is_active' => ['bail', 'required', 'boolean'],
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function assignableRoleNames(): array
    {
        $actor = $this->user();

        return $actor instanceof User ? app(AssignableRoleService::class)->namesFor($actor) : [];
    }
}
