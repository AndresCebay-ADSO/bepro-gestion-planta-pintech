<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Services\AssignableRoleService;
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
            // Sin escalada de privilegios (AssignableRoleService). El rol actual siempre está entre los asignables: la
            // policy solo deja editar a quien tiene todos los permisos del usuario, y esos son los de su rol.
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
