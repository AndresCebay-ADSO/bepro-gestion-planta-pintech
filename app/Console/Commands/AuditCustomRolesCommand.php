<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SystemRole;
use App\Services\PermissionCatalogService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

/**
 * Revisa los roles personalizados contra las reglas actuales del código. Solo informa: nunca cambia permisos
 * (decisión del usuario, docs/REVISION_RAMA_RBAC.md, lote A6).
 */
#[Signature('roles:audit')]
#[Description('Lista los roles personalizados con permisos reservados, obligatorios faltantes o dependencias incompletas')]
class AuditCustomRolesCommand extends Command
{
    public function handle(PermissionCatalogService $catalog): int
    {
        $rows = [];

        foreach ($this->customRoles() as $role) {
            foreach ($catalog->describeCustomRoleViolations($role->permissions->pluck('name')->all()) as $problem) {
                $rows[] = [$role->name, $problem];
            }
        }

        if ($rows === []) {
            $this->info('Todos los roles personalizados cumplen las reglas de permisos.');

            return self::SUCCESS;
        }

        $this->error('Hay roles personalizados que incumplen las reglas de permisos. Corrígelos desde la pantalla de roles.');
        $this->table(['Rol', 'Problema'], $rows);

        return self::FAILURE;
    }

    /**
     * @return iterable<int, Role>
     */
    private function customRoles(): iterable
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get()
            ->reject(fn (Role $role): bool => SystemRole::isSystem($role->name));
    }
}
