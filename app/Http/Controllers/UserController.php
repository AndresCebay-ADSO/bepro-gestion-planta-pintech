<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Filters\UserFilter;
use App\Http\Requests\Admin\IndexUserRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use App\Services\AssignableRoleService;
use App\Services\SignatureOptimizerService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        private readonly AssignableRoleService $assignableRoleService,
    ) {}

    /**
     * Mostrar lista de usuarios.
     */
    public function index(IndexUserRequest $request): Response
    {
        $users = (new UserFilter($request))
            ->apply(User::with('roles'))
            ->latest()
            ->paginate(15)
            ->onEachSide(1)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_label' => $user->roles->isEmpty() ? null : SystemRole::labelFor($user->roles->first()->name),
                'is_active' => (bool) $user->is_active,
                'last_login_at' => $user->last_login_at,
                'created_at' => $user->created_at,
            ]);

        // La actividad reciente es auditoría: solo con audit_logs.view (docs/MATRIZ_RBAC.md).
        $canViewActivity = $request->user()?->can(Permission::AuditLogsView->value) ?? false;
        $activities = $canViewActivity
            ? Activity::with('causer')->latest()->take(5)->get()
            : collect();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->validated(),
            'recentActivities' => $activities,
            'can' => [
                'create' => $request->user()?->can('create', User::class) ?? false,
                'delete' => $request->user()?->can(Permission::UsersDelete->value) ?? false,
                'viewActivity' => $canViewActivity,
            ],
        ]);
    }

    /**
     * Mostrar formulario para crear usuario.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create', [
            'roles' => $this->assignableRoles(),
            'defaultRole' => SystemRole::Production->value,
        ]);
    }

    /**
     * Guardar nuevo usuario en la base de datos.
     */
    public function store(StoreUserRequest $request, SignatureOptimizerService $optimizer): RedirectResponse
    {
        $validated = $request->validated();

        $signaturePath = null;
        if ($request->hasFile('signature')) {
            $signaturePath = $optimizer->optimizeAndStore($request->file('signature'));
        }

        try {
            DB::transaction(function () use ($validated, $signaturePath) {
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => ! empty($validated['phone']) ? $validated['phone'] : null,
                    'job_title' => ! empty($validated['job_title']) ? $validated['job_title'] : null,
                    'password' => $validated['password'],
                    'email_verified_at' => now(),
                    'is_active' => $validated['is_active'],
                    'signature_path' => $signaturePath,
                ]);

                $this->lockRole($validated['role']);
                $user->assignRole($validated['role']);
            });
        } catch (\Throwable $e) {
            if ($signaturePath) {
                Storage::disk('public')->delete($signaturePath);
            }

            throw $e;
        }

        return redirect()->route('users.index')->with('message', 'Usuario creado exitosamente.');
    }

    /**
     * Mostrar formulario para editar usuario.
     */
    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        $roles = $this->assignableRoles();

        $user->load('roles');

        return Inertia::render('Admin/Users/Edit', [
            'user' => $user,
            'roles' => $roles,
        ]);
    }

    /**
     * Actualizar usuario.
     */
    public function update(UpdateUserRequest $request, User $user, SignatureOptimizerService $optimizer): RedirectResponse
    {
        $validated = $request->validated();

        $currentRole = $user->roles->first()?->name;
        $roleChanged = $validated['role'] !== $currentRole;

        if ($roleChanged && ! ($request->user()?->can('manageRoles', User::class) ?? false)) {
            abort(403, 'No tienes autorización para cambiar el rol de los usuarios.');
        }

        if ($user->id === $request->user()?->id) {
            if (! $validated['is_active']) {
                return back()->with('error', 'No puedes desactivar tu propia cuenta de usuario.');
            }

            if ($roleChanged) {
                return back()->with('error', 'No puedes cambiar tu propio rol.');
            }
        }

        // Siempre debe quedar al menos un SuperAdmin y un Admin activos (docs/MATRIZ_RBAC.md §4).
        foreach ([SystemRole::SuperAdmin, SystemRole::Admin] as $protectedRole) {
            $losesProtectedRole = $user->hasRole($protectedRole->value)
                && (! $validated['is_active'] || $validated['role'] !== $protectedRole->value);

            if ($losesProtectedRole && ! $this->hasOtherActiveUserWithRole($protectedRole, $user)) {
                return back()->with('error', 'No se puede desactivar o degradar al único '.mb_strtolower($protectedRole->label()).' activo del sistema.');
            }
        }

        $oldRole = $user->roles->first()?->name ?? 'none';
        $oldSignatureToDelete = null;
        $newSignaturePath = null;

        if ($request->hasFile('signature')) {
            $newSignaturePath = $optimizer->optimizeAndStore($request->file('signature'));
            $oldSignatureToDelete = $user->signature_path;
            $validated['signature_path'] = $newSignaturePath;
        } elseif ($request->boolean('remove_signature')) {
            $oldSignatureToDelete = $user->signature_path;
            $validated['signature_path'] = null;
        }

        try {
            DB::transaction(function () use ($user, $validated) {
                $user->update([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => ! empty($validated['phone']) ? $validated['phone'] : null,
                    'job_title' => ! empty($validated['job_title']) ? $validated['job_title'] : null,
                    'is_active' => $validated['is_active'],
                    ...(array_key_exists('signature_path', $validated)
                        ? ['signature_path' => $validated['signature_path']]
                        : []),
                ]);

                $this->lockRole($validated['role']);
                $user->syncRoles([$validated['role']]);
            });
        } catch (\Throwable $e) {
            if ($newSignaturePath) {
                Storage::disk('public')->delete($newSignaturePath);
            }

            throw $e;
        }

        if ($oldSignatureToDelete) {
            Storage::disk('public')->delete($oldSignatureToDelete);
        }

        if ($oldRole !== $validated['role']) {
            activity('security')
                ->performedOn($user)
                ->event('role_changed')
                ->withProperties([
                    'old_role' => $oldRole,
                    'new_role' => $validated['role'],
                ])
                ->log("Rol de usuario modificado de {$oldRole} a ".$validated['role']);
        }

        return redirect()->route('users.index')->with('message', 'Usuario actualizado exitosamente.');
    }

    /**
     * Eliminar usuario.
     *
     * Bloquea la eliminación si el usuario:
     * - Es el mismo que está autenticado (auto-eliminación).
     * - Tiene registros en tablas con FK created_by (órdenes, fórmulas, movimientos, etc.).
     * - Tiene actividad registrada vía Spatie activity log.
     */
    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        if ($user->hasAnyRole([SystemRole::Admin->value, SystemRole::SuperAdmin->value])) {
            return back()->with('error', 'No se puede eliminar un administrador. Desactiva su cuenta en su lugar.');
        }

        if ($user->hasActivity()) {
            return back()->with('error', 'No se puede eliminar el usuario porque tiene actividad registrada en el sistema. Desactiva su cuenta en su lugar.');
        }

        try {
            $user->delete();
        } catch (QueryException $e) {
            if ((string) $e->getCode() === '23503') {
                return back()->with('error', 'No se puede eliminar el usuario porque tiene registros asociados en el sistema. Desactiva su cuenta en su lugar.');
            }

            throw $e;
        }

        return redirect()->route('users.index')->with('message', 'Usuario eliminado exitosamente.');
    }

    /**
     * Roles que el usuario autenticado puede asignar, sin escalada de privilegios (AssignableRoleService).
     *
     * @return array<int, array{id: int, name: string, label: string}>
     */
    private function assignableRoles(): array
    {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            return [];
        }

        return $this->assignableRoleService->for($actor)
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => SystemRole::labelFor($role->name),
            ])
            ->values()
            ->all();
    }

    /**
     * Bloquea la fila del rol: serializa la asignación con DeleteRoleAction, que no elimina un rol con usuarios.
     */
    private function lockRole(string $name): void
    {
        Role::query()
            ->where('name', $name)
            ->where('guard_name', 'web')
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function hasOtherActiveUserWithRole(SystemRole $role, User $user): bool
    {
        return User::role($role->value)
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->exists();
    }
}
