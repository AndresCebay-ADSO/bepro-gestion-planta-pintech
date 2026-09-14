<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Concerns\ProfileValidationRules;
use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User
            && ($this->user()?->can('update', $target) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $userId = $user instanceof User ? $user->id : (is_numeric($user) ? (int) $user : null);

        return array_merge($this->profileRules($userId), [
            // super-admin no se asigna desde el formulario (blindaje de la tarea 2.3).
            'role' => ['bail', 'required', 'string', Rule::exists('roles', 'name')->whereNot('name', SystemRole::SuperAdmin->value)],
            'is_active' => ['bail', 'required', 'boolean'],
        ]);
    }
}
