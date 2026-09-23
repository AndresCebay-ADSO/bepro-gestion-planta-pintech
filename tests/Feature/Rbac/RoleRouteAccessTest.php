<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\Support\Rbac\RoleRouteMatrix;

/**
 * Matriz de acceso comprobada por HTTP: cada rol entra de verdad en cada pantalla principal (B17).
 *
 * `RoutePermissionMapTest` revisa la tabla de rutas; este test entra por la puerta. Es la prueba que se le enseña
 * a una auditoría: demuestra que Comercial no abre una orden de producción, en vez de deducirlo del middleware.
 */
it('abre solo las pantallas que la matriz concede a cada rol', function (SystemRole $role) {
    $user = actingAsRole($role);

    $wrong = [];

    foreach (RoleRouteMatrix::screens() as $routeName => $byRole) {
        $expected = $byRole[$role->value] ? 200 : 403;
        $status = $this->get(route($routeName))->getStatusCode();

        if ($status !== $expected) {
            $wrong[] = "{$routeName}: esperado {$expected}, recibido {$status}";
        }
    }

    expect($wrong)->toBe([], "{$role->value} ({$user->email}):\n".implode("\n", $wrong));
})->with(SystemRole::cases());

it('deja abiertas a cualquier usuario autenticado las rutas sin permiso', function () {
    // Operador es el rol con menos permisos (8): si él entra, entra cualquiera con sesión.
    actingAsRole(SystemRole::Operator);

    foreach (RoleRouteMatrix::openToAnyUser() as $routeName) {
        expect($this->get(route($routeName))->getStatusCode())
            ->toBeLessThan(400, "{$routeName} debería abrirse sin permiso");
    }
});

it('exige sesión en todas las pantallas de la matriz', function () {
    $public = [];

    foreach (array_keys(RoleRouteMatrix::screens()) as $routeName) {
        if ($this->get(route($routeName))->getStatusCode() !== 302) {
            $public[] = $routeName;
        }
    }

    expect($public)->toBe([], 'Pantallas alcanzables sin sesión: '.implode(', ', $public));
});

it('cubre toda pantalla principal protegida por permiso', function () {
    // Una pantalla nueva sin entrada en la matriz queda sin comprobar: aquí se obliga a declararla.
    $uncovered = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => in_array('GET', $route->methods(), true)
            && ! str_contains($route->uri(), '{')
            && collect($route->gatherMiddleware())
                ->contains(fn ($item) => is_string($item) && str_starts_with($item, 'can:')))
        ->map(fn (RoutingRoute $route) => $route->getName())
        ->reject(fn (?string $name) => $name !== null && array_key_exists($name, RoleRouteMatrix::screens()))
        ->values()
        ->all();

    expect($uncovered)->toBe([], 'Pantallas sin fila en la matriz: '.implode(', ', $uncovered));
});
