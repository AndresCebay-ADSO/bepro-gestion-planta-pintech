<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Roles\CreateRoleAction;
use App\Actions\Roles\DeleteRoleAction;
use App\Actions\Roles\UpdateRoleAction;
use App\Enums\SystemRole;
use App\Filters\RoleFilter;
use App\Http\Requests\Roles\IndexRoleRequest;
use App\Http\Requests\Roles\StoreRoleRequest;
use App\Http\Requests\Roles\UpdateRoleRequest;
use App\Services\PermissionCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Gestión de roles (docs/MATRIZ_RBAC.md §8.3). Los roles del sistema se ven en solo lectura.
 */
class RoleController extends Controller
{
    public function __construct(
        private readonly PermissionCatalogService $permissionCatalog,
        private readonly CreateRoleAction $createRole,
        private readonly UpdateRoleAction $updateRole,
        private readonly DeleteRoleAction $deleteRole,
    ) {}

    public function index(IndexRoleRequest $request): Response
    {
        $user = $request->user();

        $roles = (new RoleFilter($request))
            ->apply(Role::query()->where('guard_name', SystemRole::GUARD)->withCount(['permissions', 'users']))
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Role $role): array => [
                'id' => $role->id,
                'label' => SystemRole::labelFor($role->name),
                'is_system' => SystemRole::isSystem($role->name),
                'permissions_count' => $role->permissions_count,
                'users_count' => $role->users_count,
                'can' => [
                    'update' => $user?->can('update', $role) ?? false,
                    'delete' => $user?->can('delete', $role) ?? false,
                ],
            ]);

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
            'filters' => $request->validated(),
            'can' => [
                'create' => $user?->can('create', Role::class) ?? false,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Role::class);

        return Inertia::render('Admin/Roles/Create', [
            'modules' => $this->permissionCatalog->modules(),
            'templates' => $this->templates(),
            'defaultPermissions' => $this->permissionCatalog->requiredForCustomRoles(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->createRole->execute($validated['name'], $validated['permissions']);

        return redirect()->route('roles.index')->with('success', 'Rol creado exitosamente.');
    }

    public function show(Request $request, Role $role): Response
    {
        $this->authorize('view', $role);

        $user = $request->user();

        return Inertia::render('Admin/Roles/Show', [
            'role' => [
                'id' => $role->id,
                'label' => SystemRole::labelFor($role->name),
                'is_system' => SystemRole::isSystem($role->name),
                'users_count' => $role->users()->count(),
                'permissions' => $role->permissions->pluck('name')->values()->all(),
            ],
            'modules' => $this->permissionCatalog->modules(includeReserved: true),
            'can' => [
                'update' => $user?->can('update', $role) ?? false,
                'delete' => $user?->can('delete', $role) ?? false,
            ],
        ]);
    }

    public function edit(Role $role): Response
    {
        $this->authorize('update', $role);

        return Inertia::render('Admin/Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values()->all(),
            ],
            'modules' => $this->permissionCatalog->modules(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $validated = $request->validated();

        $this->updateRole->execute($role, $validated['name'], $validated['permissions']);

        return redirect()->route('roles.index')->with('success', 'Rol actualizado exitosamente.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        try {
            $this->deleteRole->execute($role);
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('roles.index')->with('success', 'Rol eliminado exitosamente.');
    }

    /**
     * Roles existentes como punto de partida al crear uno nuevo, sin los permisos reservados.
     *
     * @return array<int, array{id: int, label: string, permissions: array<int, string>}>
     */
    private function templates(): array
    {
        $assignable = $this->permissionCatalog->assignableToCustomRoles();

        return Role::query()
            ->where('guard_name', SystemRole::GUARD)
            ->with('permissions:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'label' => SystemRole::labelFor($role->name),
                'permissions' => $role->permissions->pluck('name')->intersect($assignable)->values()->all(),
            ])
            ->all();
    }
}
