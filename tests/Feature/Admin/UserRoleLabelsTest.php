<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use Inertia\Testing\AssertableInertia as Assert;

it('envía la etiqueta de cada rol asignable en los formularios de usuario', function (string $routeName) {
    actingAsRole(SystemRole::SuperAdmin);
    $target = userWithRole(SystemRole::Operator);

    $url = $routeName === 'users.edit' ? route($routeName, $target) : route($routeName);

    $expected = collect(SystemRole::cases())
        ->mapWithKeys(fn (SystemRole $role) => [$role->value => $role->label()])
        ->sortKeys()
        ->all();

    $this->get($url)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('roles', fn ($roles) => collect($roles)->pluck('label', 'name')->sortKeys()->all() === $expected));
})->with(['users.create', 'users.edit']);

it('no preselecciona ningún rol al crear un usuario', function () {
    actingAsRole(SystemRole::Admin);

    $this->get(route('users.create'))
        ->assertInertia(fn (Assert $page) => $page->missing('defaultRole'));
});

it('muestra la etiqueta del rol en el listado de usuarios, sin enviar el modelo completo', function () {
    actingAsRole(SystemRole::Admin);
    $production = userWithRole(SystemRole::Production);

    $this->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.data', function ($users) use ($production) {
                $row = collect($users)->firstWhere('id', $production->id);

                return $row['role_label'] === 'Producción'
                    && ! array_key_exists('roles', $row)
                    && ! array_key_exists('signature_path', $row);
            }));
});

it('resuelve la etiqueta de un rol del sistema y conserva el nombre de un rol personalizado', function () {
    expect(SystemRole::labelFor(SystemRole::SuperAdmin->value))->toBe('Super administrador')
        ->and(SystemRole::labelFor('calidad'))->toBe('calidad');
});
