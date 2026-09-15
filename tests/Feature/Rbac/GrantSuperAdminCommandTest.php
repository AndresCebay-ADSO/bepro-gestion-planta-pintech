<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('otorga SuperAdmin a un usuario existente y activo, reemplazando su rol', function () {
    $user = userWithRole(SystemRole::Admin, ['email' => 'soporte@empresa.com', 'is_active' => true]);

    $this->artisan('users:grant-super-admin', ['email' => 'SOPORTE@empresa.com', '--force' => true])
        ->assertSuccessful();

    $user->refresh();
    expect($user->isSuperAdmin())->toBeTrue()
        ->and($user->hasRole(SystemRole::Admin->value))->toBeFalse()
        ->and(Activity::where('log_name', 'security')->where('event', 'role_changed')->where('subject_id', $user->id)->exists())->toBeTrue();
});

it('pide confirmación y no cambia nada si se cancela', function () {
    $user = userWithRole(SystemRole::Operator, ['email' => 'operador@empresa.com', 'is_active' => true]);

    $this->artisan('users:grant-super-admin', ['email' => 'operador@empresa.com'])
        ->expectsConfirmation("¿Asignar SuperAdmin a {$user->name} <operador@empresa.com>? Reemplaza su rol actual (operador).", 'no')
        ->assertFailed();

    expect($user->fresh()->isSuperAdmin())->toBeFalse();
});

it('falla con un correo inexistente o un usuario inactivo', function () {
    $this->artisan('users:grant-super-admin', ['email' => 'nadie@empresa.com', '--force' => true])
        ->assertFailed();

    $inactive = userWithRole(SystemRole::Operator, ['email' => 'inactivo@empresa.com', 'is_active' => false]);

    $this->artisan('users:grant-super-admin', ['email' => 'inactivo@empresa.com', '--force' => true])
        ->assertFailed();

    expect($inactive->fresh()->isSuperAdmin())->toBeFalse();
});

it('siembra un usuario de soporte SuperAdmin en local y testing', function () {
    $this->seed(UserSeeder::class);

    expect(User::where('email', 'soporte@pintech.test')->first()?->isSuperAdmin())->toBeTrue();
});
