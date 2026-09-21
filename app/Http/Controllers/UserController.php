<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Shared\DeleteUnusedRecordAction;
use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Filters\UserFilter;
use App\Http\Requests\Admin\IndexUserRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use App\Services\AssignableRoleService;
use App\Services\SignatureOptimizerService;
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
        private readonly DeleteUnusedRecordAction $deleteUnused,
    ) {}

    /**
     * Mostrar lista de usuarios.
     */
    public function index(IndexUserRequest $request): Response
    {
        $actor = $request->user();

        // roles.permissions y permissions: la policy compara los permisos de cada fila con los de quien consulta.
        $users = (new UserFilter($request))
            ->apply(User::with(['roles.permissions', 'permissions']))
            ->latest()
            ->paginate(15)
            ->onEachSide(1)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_label' => SystemRole::labelFor($user->roles->first()?->name),
                'is_active' => (bool) $user->is_active,
                'last_login_at' => $user->last_login_at,
                'created_at' => $user->created_at,
                // Por fila, como en roles: sin esto la tabla ofrece acciones que la policy rechaza con 403. La propia
                // cuenta no se elimina (destroy).
                'can' => [
                    'update' => $actor?->can('update', $user) ?? false,
                    'delete' => ($actor?->can('delete', $user) ?? false) && $user->id !== $actor?->id,
                ],
            ]);

        // La actividad reciente es auditoría: solo con audit_logs.view (docs/MATRIZ_RBAC.md).
        $canViewActivity = $actor?->can(Permission::AuditLogsView->value) ?? false;
        $activities = $canViewActivity
            ? Activity::with('causer')->latest()->take(5)->get()
            : collect();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->validated(),
            'recentActivities' => $activities,
            'can' => [
                'create' => $actor?->can('create', User::class) ?? false,
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

        $user->load('roles');

        return Inertia::render('Admin/Users/Edit', [
            'user' => $user,
            'roles' => $this->assignableRoles(),
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

        // Siempre debe quedar al menos un SuperAdmin activo, o nadie podría recuperar el acceso (docs/MATRIZ_RBAC.md §4).
        // Es la única regla ligada a un rol: los demás usuarios se protegen por permisos (UserPolicy).
        $losesSuperAdmin = $user->isSuperAdmin()
            && (! $validated['is_active'] || $validated['role'] !== SystemRole::SuperAdmin->value);

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
            $updated = DB::transaction(function () use ($user, $validated, $roleChanged, $losesSuperAdmin): bool {
                if ($losesSuperAdmin) {
                    // Bloquear el rol serializa las degradaciones simultáneas: sin esto, dos SuperAdmins que se
                    // desactivan a la vez verían al otro todavía activo y no quedaría ninguno.
                    $this->lockRole(SystemRole::SuperAdmin->value);

                    if (! $this->hasOtherActiveSuperAdmin($user)) {
                        return false;
                    }
                }

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

                if ($roleChanged) {
                    $this->lockRole($validated['role']);
                    $user->syncRoles([$validated['role']]);
                }

                return true;
            });
        } catch (\Throwable $e) {
            if ($newSignaturePath) {
                Storage::disk('public')->delete($newSignaturePath);
            }

            throw $e;
        }

        if (! $updated) {
            if ($newSignaturePath) {
                Storage::disk('public')->delete($newSignaturePath);
            }

            return back()->with('error', 'No se puede desactivar o degradar al único super administrador activo del sistema.');
        }

        if ($oldSignatureToDelete) {
            Storage::disk('public')->delete($oldSignatureToDelete);
        }

        if ($roleChanged) {
            activity('security')
                ->performedOn($user)
                ->event('role_changed')
                ->withProperties([
                    'old_role' => $currentRole,
                    'new_role' => $validated['role'],
                ])
                ->log('Rol de usuario modificado de '.SystemRole::labelFor($currentRole).' a '
                    .SystemRole::labelFor($validated['role']));
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

        // La autoría en la auditoría no tiene clave foránea; el resto del historial lo protegen las claves foráneas.
        if ($user->hasActivity()) {
            return back()->with('error', 'No se puede eliminar el usuario porque tiene actividad registrada en el sistema. Desactiva su cuenta en su lugar.');
        }

        $result = DB::transaction(function () use ($user): string {
            // Mismo bloqueo que update(): dos SuperAdmins que se eliminan o desactivan a la vez no pueden dejar el
            // sistema sin ninguno activo.
            if ($user->isSuperAdmin() && $user->is_active) {
                $this->lockRole(SystemRole::SuperAdmin->value);

                if (! $this->hasOtherActiveSuperAdmin($user)) {
                    return 'last_super_admin';
                }
            }

            return $this->deleteUnused->execute($user) ? 'deleted' : 'has_history';
        });

        if ($result === 'last_super_admin') {
            return back()->with('error', 'No se puede eliminar al único super administrador activo del sistema.');
        }

        if ($result === 'has_history') {
            return back()->with('error', 'No se puede eliminar el usuario porque tiene registros asociados en el sistema. Desactiva su cuenta en su lugar.');
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
            ->map(fn (Role $role): array => $this->roleOption($role))
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, name: string, label: string}
     */
    private function roleOption(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'label' => SystemRole::labelFor($role->name),
        ];
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

    private function hasOtherActiveSuperAdmin(User $user): bool
    {
        return User::superAdmins()->active()->whereKeyNot($user->id)->exists();
    }
}
