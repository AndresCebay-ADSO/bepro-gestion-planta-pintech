<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Tests\Support\Rbac\RoleRouteMatrix;
use Tests\Support\Rbac\RoutePermissionMap;

/**
 * Matriz de acceso comprobada por HTTP: cada rol entra de verdad en cada pantalla principal (B17).
 *
 * `RoutePermissionMapTest` revisa la tabla de rutas; este test entra por la puerta.
 *
 * Alcance: las pantallas listables, es decir las rutas GET **sin parámetros**. Las de detalle y edición
 * (`production-orders/{id}`, `quotations/{id}/edit`…) no se recorren aquí porque exigen fabricar un registro de
 * cada modelo; su autorización se comprueba en las pruebas de policy (`ProductionOrderPolicyTest` y las de cada
 * módulo) y en `RoutePermissionMapTest`, que exige la ability exacta de cada una.
 */
it('abre solo las pantallas que la matriz concede a cada rol', function (SystemRole $role) {
    $user = actingAsRole($role);

    $wrong = [];

    foreach (RoleRouteMatrix::screens() as $routeName => $byRole) {
        $expected = $byRole[$role->value] ? 200 : 403;
        $response = $this->get(route($routeName));

        if ($response->getStatusCode() !== $expected) {
            $wrong[] = "{$routeName}: esperado {$expected}, recibido ".describeResponse($response);
        }
    }

    expect($wrong)->toBe([], "{$role->value} ({$user->email}):\n".implode("\n", $wrong));
})->with(SystemRole::cases());

it('deja abiertas a cualquier usuario autenticado las rutas sin permiso', function () {
    // Un usuario sin rol ni permisos, que es lo que afirma el contrato. Con un Operador no valdría: tiene ocho
    // permisos, y si una de estas rutas ganara uno de ellos el test seguiría verde mientras los demás pierden acceso.
    $this->actingAs(User::factory()->create());

    $wrong = [];

    foreach (RoleRouteMatrix::openToAnyUser() as $routeName => $expected) {
        $response = $this->get(route($routeName));
        $target = isset($expected['to']) ? route($expected['to']) : null;

        if ($response->getStatusCode() !== $expected['status']
            || ($target !== null && $response->headers->get('Location') !== $target)) {
            $wrong[] = "{$routeName}: esperado {$expected['status']}"
                .($target !== null ? " -> {$target}" : '').', recibido '.describeResponse($response);
        }
    }

    expect($wrong)->toBe([], implode("\n", $wrong));
});

it('declara toda ruta de la aplicación que se abre sin permiso', function () {
    // Las marcadas AUTHENTICATED en el mapa y alcanzables como pantalla deben comprobarse aquí.
    $undeclared = collect(RoutePermissionMap::applicationRoutes())
        ->filter(fn (RoutingRoute $route) => in_array('GET', $route->methods(), true)
            && ! str_contains($route->uri(), '{')
            && (RoutePermissionMap::routes()[$route->getName()] ?? null) === RoutePermissionMap::AUTHENTICATED)
        ->map(fn (RoutingRoute $route) => $route->getName())
        ->reject(fn (?string $name) => $name !== null && array_key_exists($name, RoleRouteMatrix::openToAnyUser()))
        ->values()
        ->all();

    expect($undeclared)->toBe([], 'Rutas sin permiso que nadie comprueba: '.implode(', ', $undeclared));
});

it('manda al login toda pantalla cuando no hay sesión', function () {
    // También las que no piden permiso: si alguien retira el middleware `auth` del perfil, debe saltar aquí.
    $routes = array_merge(
        array_keys(RoleRouteMatrix::screens()),
        array_keys(RoleRouteMatrix::openToAnyUser()),
    );

    $wrong = [];

    foreach ($routes as $routeName) {
        $response = $this->get(route($routeName));

        // El destino importa: un 302 a cualquier otro sitio no demuestra que exija sesión.
        if ($response->headers->get('Location') !== route('login')) {
            $wrong[] = "{$routeName}: ".describeResponse($response);
        }
    }

    expect($wrong)->toBe([], "Pantallas que no mandan al login:\n".implode("\n", $wrong));
});

it('cubre toda pantalla principal protegida por permiso', function () {
    // Una pantalla nueva sin entrada en la matriz queda sin comprobar: aquí se obliga a declararla.
    $uncovered = collect(RoutePermissionMap::applicationRoutes())
        ->filter(fn (RoutingRoute $route) => in_array('GET', $route->methods(), true)
            && ! str_contains($route->uri(), '{')
            && collect($route->gatherMiddleware())
                ->contains(fn ($item) => is_string($item)
                    && (str_starts_with($item, 'can:') || str_starts_with($item, 'permission:'))))
        ->map(fn (RoutingRoute $route) => $route->getName())
        ->reject(fn (?string $name) => $name !== null && array_key_exists($name, RoleRouteMatrix::screens()))
        ->values()
        ->all();

    expect($uncovered)->toBe([], 'Pantallas sin fila en la matriz: '.implode(', ', $uncovered));
});
