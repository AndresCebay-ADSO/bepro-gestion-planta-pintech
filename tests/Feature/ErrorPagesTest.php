<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

it('muestra la página 403 con los datos compartidos cuando falta el permiso', function () {
    actingAsRole(SystemRole::Operator);

    $this->get(route('users.index'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ErrorPage')
            ->where('status', 403)
            ->whereNot('auth.user', null));
});

it('muestra la página 404 en una ruta inexistente, sin sesión', function () {
    $this->get('/ruta-que-no-existe')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ErrorPage')
            ->where('status', 404));
});

it('muestra la página 404 cuando el registro no existe', function () {
    actingAsRole(SystemRole::Admin);

    $this->get(route('users.edit', 999999))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ErrorPage')
            ->where('status', 404));
});

it('muestra la página 419 cuando la sesión o el token expiraron', function () {
    Route::middleware('web')->post('/_error-pages-test', fn () => abort(419));

    $this->post('/_error-pages-test')
        ->assertStatus(419)
        ->assertInertia(fn (Assert $page) => $page
            ->component('ErrorPage')
            ->where('status', 419));
});

it('mantiene la respuesta JSON en peticiones que esperan JSON', function () {
    $this->getJson('/ruta-que-no-existe')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});

it('muestra la página 500 cuando APP_DEBUG está desactivado', function () {
    config(['app.debug' => false]);
    Route::middleware('web')->get('/_error-pages-test', fn () => throw new RuntimeException('Fallo de prueba'));

    $this->get('/_error-pages-test')
        ->assertStatus(500)
        ->assertInertia(fn (Assert $page) => $page
            ->component('ErrorPage')
            ->where('status', 500));
});

it('conserva la traza de Laravel en un 500 cuando APP_DEBUG está activo', function () {
    config(['app.debug' => true]);
    Route::middleware('web')->get('/_error-pages-test', fn () => throw new RuntimeException('Fallo de prueba'));

    $response = $this->get('/_error-pages-test')->assertStatus(500);

    expect($response->original)->not->toBeInstanceOf(View::class);
});
