<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Filters\UserFilter;
use App\Http\Requests\Admin\IndexUserRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
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
            ->withQueryString();

        $activities = Activity::with('causer')
            ->latest()
            ->take(5)
            ->get();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->validated(),
            'recentActivities' => $activities,
            'can' => [
                'create' => $request->user()?->hasRole('admin') ?? false,
            ],
        ]);
    }

    /**
     * Mostrar formulario para crear usuario.
     */
    public function create(): Response
    {
        $roles = Role::all();

        return Inertia::render('Admin/Users/Create', [
            'roles' => $roles,
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
        $roles = Role::all();

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

        if ($user->id === $request->user()?->id) {
            if (! $validated['is_active']) {
                return back()->with('error', 'No puedes desactivar tu propia cuenta de usuario.');
            }

            if ($validated['role'] !== 'admin') {
                return back()->with('error', 'No puedes revocar tu propio rol de administrador.');
            }
        }

        $isDemotingOrDeactivatingAdmin = $user->hasRole('admin')
            && (! $validated['is_active'] || $validated['role'] !== 'admin');

        if ($isDemotingOrDeactivatingAdmin) {
            $hasOtherActiveAdmin = User::role('admin')
                ->where('is_active', true)
                ->where('id', '!=', $user->id)
                ->exists();

            if (! $hasOtherActiveAdmin) {
                return back()->with('error', 'No se puede desactivar o degradar al único administrador activo del sistema.');
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
        if ($user->id === auth()->id()) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        if ($user->hasRole('admin')) {
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
}
