<?php

declare(strict_types=1);

namespace App\Http\Requests\Roles;

use Spatie\Permission\Models\Role;

class StoreRoleRequest extends RoleFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Role::class) ?? false;
    }

    protected function ignoredRoleId(): ?int
    {
        return null;
    }
}
