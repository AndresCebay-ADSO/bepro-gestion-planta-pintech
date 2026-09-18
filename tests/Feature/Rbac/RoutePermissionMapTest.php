<?php

declare(strict_types=1);

use App\Enums\Permission;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;
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

it('exige el permiso del mapa en las rutas que ya no dependen de role:', function () {
    $mismatches = [];

    foreach (applicationRoutes() as $route) {
        $entry = RoutePermissionMap::routes()[$route->getName()] ?? null;

        // Los marcadores (pública, con sesión, a retirar) no llevan permiso.
        if ($entry === null || is_string($entry)) {
            continue;
        }

        $middleware = $route->gatherMiddleware();

        // Ruta aún no migrada (tarea 2.2): sigue protegida por rol.
        if (collect($middleware)->contains(fn ($item) => is_string($item) && str_starts_with($item, 'role:'))) {
            continue;
        }

        // Listas (basta con uno de varios permisos): se autorizan con la policy del modelo (can:viewAny / can:view).
        if (is_array($entry)) {
            if (! collect($middleware)->contains(fn ($item) => is_string($item) && str_starts_with($item, 'can:'))) {
                $mismatches[] = "{$route->getName()} (esperado un middleware can: de policy)";
            }

            continue;
        }

        if (! in_array('can:'.$entry->value, $middleware, true)) {
            $mismatches[] = "{$route->getName()} (esperado can:{$entry->value})";
        }
    }

    expect($mismatches)->toBe([], 'Rutas sin su permiso: '.implode(', ', $mismatches));
});

it('no protege ninguna ruta por rol', function () {
    $byRole = collect(applicationRoutes())
        ->filter(fn (RoutingRoute $route) => collect($route->gatherMiddleware())
            ->contains(fn ($item) => is_string($item) && str_starts_with($item, 'role:')))
        ->map(fn (RoutingRoute $route) => $route->getName() ?? $route->uri())
        ->values()
        ->all();

    expect($byRole)->toBe([], 'Rutas que aún usan role: '.implode(', ', $byRole));
});

it('no decide por nombre de rol fuera de User', function () {
    // Solo User::isSuperAdmin() y User::superAdmins() consultan un rol: el resto del código decide por permisos. Los
    // seeders (database/) sí asignan roles concretos para los datos de demo.
    $pattern = '/->(hasRole|hasAnyRole|hasAllRoles|hasExactRoles|role|withoutRole)\(|::(role|withoutRole)\(/';

    $offenders = collect(File::allFiles(app_path()))
        ->reject(fn (SplFileInfo $file) => $file->getRealPath() === app_path('Models/User.php'))
        ->filter(fn (SplFileInfo $file) => preg_match($pattern, $file->getContents()) === 1)
        ->map(fn (SplFileInfo $file) => str_replace(base_path().'/', '', $file->getRealPath()))
        ->values()
        ->all();

    expect($offenders)->toBe([], 'Comprobaciones por rol fuera de User: '.implode(', ', $offenders));
});

it('autoriza con la policy del registro las acciones sobre registros con dueño', function (string $routeName) {
    $middleware = Route::getRoutes()->getByName($routeName)?->gatherMiddleware() ?? [];

    // Además del permiso, la policy del modelo comprueba dueño y estado ya en la ruta (defensa en profundidad).
    $policyMiddleware = collect($middleware)
        ->filter(fn ($item) => is_string($item) && str_starts_with($item, 'can:') && str_contains($item, ','));

    expect($policyMiddleware)->not->toBeEmpty("{$routeName} no autoriza con la policy del registro");
})->with([
    'quotations.show', 'quotations.edit', 'quotations.update', 'quotations.update-status',
    'quotations.convert-to-order', 'quotations.export-pdf',
    'sales-orders.show', 'sales-orders.update', 'sales-orders.update-status',
    'paint-development-requests.show', 'paint-development-requests.edit', 'paint-development-requests.update',
    'paint-development-requests.submit', 'paint-development-requests.update-status', 'paint-development-requests.export-pdf',
]);
