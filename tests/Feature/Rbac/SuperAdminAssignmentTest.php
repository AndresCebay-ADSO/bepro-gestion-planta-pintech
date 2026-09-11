<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use Inertia\Testing\AssertableInertia as Assert;

it('no ofrece el rol super-admin en el formulario de usuarios', function (string $routeName) {
    actingAsRole(SystemRole::Admin);
    $target = userWithRole(SystemRole::Operator);

    $url = $routeName === 'users.edit' ? route($routeName, $target) : route($routeName);

    $this->get($url)->assertInertia(fn (Assert $page) => $page
        ->where('roles', fn ($roles) => collect($roles)->pluck('name')->doesntContain(SystemRole::SuperAdmin->value)
            && collect($roles)->pluck('name')->contains(SystemRole::Admin->value)));
})->with(['users.create', 'users.edit']);

it('rechaza asignar super-admin al crear un usuario aunque se envíe a mano', function () {
    actingAsRole(SystemRole::Admin);

    $this->post(route('users.store'), ['role' => SystemRole::SuperAdmin->value])
        ->assertSessionHasErrors('role');
});

it('rechaza asignar super-admin al editar un usuario aunque se envíe a mano', function () {
    actingAsRole(SystemRole::Admin);
    $target = userWithRole(SystemRole::Operator);

    $this->put(route('users.update', $target), ['role' => SystemRole::SuperAdmin->value])
        ->assertSessionHasErrors('role');

    expect($target->fresh()->hasRole(SystemRole::SuperAdmin->value))->toBeFalse();
});

it('sigue aceptando los demás roles del sistema', function () {
    actingAsRole(SystemRole::Admin);

    $this->post(route('users.store'), ['role' => SystemRole::Production->value])
        ->assertSessionDoesntHaveErrors('role');
});
