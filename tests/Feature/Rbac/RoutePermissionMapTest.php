<?php

declare(strict_types=1);

use App\Enums\Permission;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Support\Rbac\RoutePermissionMap;

/**
 * @return array<int, RoutingRoute>
 */
function applicationRoutes(): array
{
    return array_values(array_filter(
        Route::getRoutes()->getRoutes(),
        fn (RoutingRoute $route) => ! in_array($route->getName(), RoutePermissionMap::IGNORED_ROUTES, true)
            && ! Str::startsWith($route->getActionName(), RoutePermissionMap::IGNORED_ACTION_PREFIXES),
    ));
}

/**
 * @return array<int, Permission>
 */
function mappedPermissions(): array
{
    $permissions = [];

    foreach (RoutePermissionMap::routes() as $entry) {
        foreach (is_array($entry) ? $entry : [$entry] as $item) {
            if ($item instanceof Permission) {
                $permissions[$item->value] = $item;
            }
        }
    }

    return array_values($permissions);
}

it('clasifica cada ruta nombrada de la aplicación', function () {
    $unclassified = collect(applicationRoutes())
        ->map(fn (RoutingRoute $route) => $route->getName())
        ->filter()
        ->reject(fn (string $name) => array_key_exists($name, RoutePermissionMap::routes()))
        ->values()
        ->all();

    expect($unclassified)->toBe([], 'Rutas sin clasificar en RoutePermissionMap: '.implode(', ', $unclassified));
});

it('solo permite rutas sin nombre en la lista blanca', function () {
    $unnamed = collect(applicationRoutes())
        ->filter(fn (RoutingRoute $route) => $route->getName() === null)
        ->map(fn (RoutingRoute $route) => $route->uri())
        ->reject(fn (string $uri) => in_array($uri, RoutePermissionMap::UNNAMED_URIS, true))
        ->values()
        ->all();

    expect($unnamed)->toBe([], 'Rutas sin nombre no permitidas: '.implode(', ', $unnamed));
});

it('no contiene rutas que ya no existen', function () {
    $stale = array_values(array_filter(
        array_keys(RoutePermissionMap::routes()),
        fn (string $name) => ! Route::has($name),
    ));

    expect($stale)->toBe([], 'Rutas del mapa que no existen: '.implode(', ', $stale));
});

it('solo usa marcadores conocidos o permisos del registro', function () {
    $markers = [RoutePermissionMap::PUBLIC, RoutePermissionMap::AUTHENTICATED, RoutePermissionMap::TO_REMOVE];

    foreach (RoutePermissionMap::routes() as $name => $entry) {
        if (is_string($entry)) {
            expect($markers)->toContain($entry);

            continue;
        }

        foreach (is_array($entry) ? $entry : [$entry] as $item) {
            expect($item)->toBeInstanceOf(Permission::class, "Entrada inválida en {$name}");
        }
    }
});

it('usa cada permiso en alguna ruta o justifica por qué no tiene', function () {
    $withRoute = array_map(fn (Permission $permission) => $permission->value, mappedPermissions());
    $withoutRoute = array_keys(RoutePermissionMap::permissionsWithoutRoute());

    $orphans = array_values(array_diff(
        array_map(fn (Permission $permission) => $permission->value, Permission::cases()),
        $withRoute,
        $withoutRoute,
    ));

    expect($orphans)->toBe([], 'Permisos sin ruta ni justificación: '.implode(', ', $orphans))
        ->and(array_intersect($withRoute, $withoutRoute))->toBe([], 'Permisos justificados como sin ruta que sí tienen ruta.');

    foreach ($withoutRoute as $value) {
        expect(Permission::tryFrom($value))->not->toBeNull("Permiso desconocido: {$value}");
    }
});
