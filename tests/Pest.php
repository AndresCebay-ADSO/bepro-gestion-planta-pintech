<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
