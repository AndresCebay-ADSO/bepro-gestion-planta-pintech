<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Mostrar lista de usuarios.
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search');

        $query = User::with('roles');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query
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
            'filters' => [
                'search' => $search,
            ],
            'recentActivities' => $activities,
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
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|exists:roles,name',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'email_verified_at' => now(),
        ]);

        $user->assignRole($validated['role']);

        return redirect()->route('users.index')->with('message', 'Usuario creado exitosamente.');
    }

    /**
     * Mostrar formulario para editar usuario.
     */
    public function edit(User $user): Response
    {
        $roles = Role::all();

        return Inertia::render('Admin/Users/Edit', [
            'user' => $user->load('roles'),
            'roles' => $roles,
        ]);
    }

    /**
     * Actualizar usuario.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'role' => 'required|exists:roles,name',
        ]);

        $oldRole = $user->roles->first()?->name ?? 'none';

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        $user->syncRoles([$validated['role']]);

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
     */
    public function destroy(User $user): RedirectResponse
    {
        $user->delete();

        return redirect()->route('users.index')->with('message', 'Usuario eliminado exitosamente.');
    }
}
