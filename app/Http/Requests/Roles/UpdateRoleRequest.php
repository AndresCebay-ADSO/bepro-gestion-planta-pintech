<?php

declare(strict_types=1);

namespace App\Http\Requests\Roles;

use Spatie\Permission\Models\Role;

class UpdateRoleRequest extends RoleFormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role && ($this->user()?->can('update', $role) ?? false);
    }

    protected function ignoredRoleId(): ?int
    {
        $role = $this->route('role');

        return $role instanceof Role ? $role->id : null;
    }
}
