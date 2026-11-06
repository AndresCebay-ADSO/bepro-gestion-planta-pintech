<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\PendingCommand;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/** Corre el comando respondiendo nombre, correo y las dos contraseñas. */
function createAdmin(string $name, string $email, string $password, ?string $confirmation = null): PendingCommand
{
    return test()->artisan('users:create-admin')
        ->expectsQuestion('Nombre completo', $name)
        ->expectsQuestion('Correo', $email)
        ->expectsQuestion('Contraseña', $password)
        ->expectsQuestion('Repite la contraseña', $confirmation ?? $password);
}

it('crea un SuperAdmin activo y verificado, con el correo en minúsculas', function () {
    createAdmin('Andrés Soporte', ' Andres@Empresa.com ', 'clave-segura-123')->assertSuccessful();

    $user = User::query()->where('email', 'andres@empresa.com')->sole();

    expect($user->isSuperAdmin())->toBeTrue()
        ->and($user->is_active)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('clave-segura-123', $user->password))->toBeTrue()
        ->and(Activity::query()->where('log_name', 'security')->where('event', 'role_changed')->where('subject_id', $user->id)->exists())
        ->toBeTrue();
});

it('no crea nada si los datos no pasan la validación', function (string $name, string $email, string $password, string $confirmation) {
    User::factory()->create(['email' => 'existe@empresa.com']);

    createAdmin($name, $email, $password, $confirmation)->assertFailed();

    expect(User::query()->count())->toBe(1);
})->with([
    'sin nombre' => ['', 'nuevo@empresa.com', 'clave-segura-123', 'clave-segura-123'],
    'correo repetido' => ['Nuevo', 'EXISTE@empresa.com', 'clave-segura-123', 'clave-segura-123'],
    'correo sin dominio' => ['Nuevo', 'nuevo@empresa', 'clave-segura-123', 'clave-segura-123'],
    'contraseñas distintas' => ['Nuevo', 'nuevo@empresa.com', 'clave-segura-123', 'otra-clave-1234'],
]);

it('fuera de local y testing exige la contraseña fuerte', function () {
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
    app()->detectEnvironment(fn () => 'staging');

    createAdmin('Nuevo', 'nuevo@empresa.com', 'clave-segura-123')->assertFailed();
    expect(User::query()->exists())->toBeFalse();

    createAdmin('Nuevo', 'nuevo@empresa.com', 'Clave-Segura-2026!')->assertSuccessful();
    expect(User::query()->where('email', 'nuevo@empresa.com')->sole()->isSuperAdmin())->toBeTrue();
});

it('falla si el rol super-admin no existe', function () {
    Role::query()->where('name', SystemRole::SuperAdmin->value)->delete();

    $this->artisan('users:create-admin')->assertFailed();

    expect(User::query()->exists())->toBeFalse();
});
