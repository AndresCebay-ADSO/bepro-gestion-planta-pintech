<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\User;
use App\Services\TimezoneService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Hoy en la planta (`Y-m-d`), para las fechas que un test envía a un formulario. `now()->toDateString()` es el día en
 * UTC: de 7 p. m. a medianoche en Bogotá ya es mañana, y PlantDate lo rechaza como fecha futura (auditoría 3.6, PR 3).
 */
function plantToday(): string
{
    return app(TimezoneService::class)->todayInPlant();
}

/**
 * Crea un usuario con un rol del sistema y los permisos de la matriz ya sembrados.
 *
 * @param  array<string, mixed>  $attributes
 */
function userWithRole(SystemRole $role, array $attributes = []): User
{
    test()->seed(RolePermissionSeeder::class);

    $user = User::factory()->create($attributes);
    $user->assignRole($role->value);

    return $user;
}

/**
 * Usuario con un rol personalizado que solo tiene los permisos dados.
 *
 * @param  list<Permission>  $permissions
 */
function userWithPermissions(array $permissions): User
{
    test()->seed(RolePermissionSeeder::class);

    $role = Role::create(['name' => 'Rol de prueba '.uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo(array_map(fn (Permission $permission): string => $permission->value, $permissions));

    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

/**
 * Autentica un usuario con un rol del sistema y lo devuelve.
 *
 * @param  array<string, mixed>  $attributes
 */
function actingAsRole(SystemRole $role, array $attributes = []): User
{
    $user = userWithRole($role, $attributes);
    test()->actingAs($user);

    return $user;
}

/**
 * Describe una respuesta para un informe de fallos, incluida la excepción si el servidor reventó.
 *
 * Sin esto, un 500 aparece como un número suelto y obliga a depurar a ciegas.
 */
function describeResponse(TestResponse $response): string
{
    $status = $response->getStatusCode();
    $exception = $response->exception;

    if ($exception !== null) {
        return $status.' ('.$exception::class.': '.$exception->getMessage().')';
    }

    return $status.($status === 302 ? ' -> '.($response->headers->get('Location') ?? 'sin destino') : '');
}
