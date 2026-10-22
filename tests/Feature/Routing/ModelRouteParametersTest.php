<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\Product;
use App\Support\ModelRouteParameters;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
 * Auditoría 3.6, PR 10 (T-01): un ID que no es un número en la URL (`/products/abc`) llegaba a la consulta y PostgreSQL
 * respondía 500 (`invalid input syntax for type bigint`). Todo parámetro de ruta que se resuelve a un modelo tiene el
 * mismo patrón numérico, declarado en un solo lugar (`ModelRouteParameters`).
 */

/**
 * Los parámetros de ruta que Laravel resuelve a un modelo, por el nombre que tienen en la URL: `{raw_material}`, no el
 * `$rawMaterial` del controlador. Es la misma búsqueda que hace el binding implícito.
 *
 * @return array<string, list<string>> nombre del parámetro => rutas donde aparece
 */
function modelBoundRouteParameters(): array
{
    $found = [];

    /** @var RoutingRoute $route */
    foreach (Route::getRoutes()->getRoutes() as $route) {
        $uriNames = $route->parameterNames();

        foreach ($route->signatureParameters(['subClass' => UrlRoutable::class]) as $parameter) {
            $name = in_array($parameter->getName(), $uriNames, true) ? $parameter->getName() : Str::snake($parameter->getName());

            if (in_array($name, $uriNames, true)) {
                $found[$name][] = $route->uri();
            }
        }
    }

    return $found;
}

it('declares the numeric pattern for every route parameter that resolves to a model', function () {
    $declared = ModelRouteParameters::NAMES;
    $missing = array_diff(array_keys(modelBoundRouteParameters()), $declared);

    expect($missing)->toBe([], 'Parámetros con modelo sin patrón en ModelRouteParameters::NAMES: '.implode(', ', $missing));
});

it('only declares parameters that some route actually uses', function () {
    $unused = array_diff(ModelRouteParameters::NAMES, array_keys(modelBoundRouteParameters()));

    expect($unused)->toBe([], 'Nombres sobrantes en ModelRouteParameters::NAMES: '.implode(', ', $unused));
});

it('applies the pattern to the registered routes without a manual where that overrides it', function () {
    $problems = [];

    /** @var RoutingRoute $route */
    foreach (Route::getRoutes()->getRoutes() as $route) {
        foreach (array_intersect($route->parameterNames(), ModelRouteParameters::NAMES) as $name) {
            if (($route->wheres[$name] ?? null) !== ModelRouteParameters::PATTERN) {
                $problems[] = $route->uri().' {'.$name.'}: '.($route->wheres[$name] ?? 'sin patrón');
            }
        }
    }

    expect($problems)->toBe([]);
});

it('answers 404 for ids that are not numbers or do not fit a bigint', function (string $url) {
    actingAsRole(SystemRole::Admin, ['email_verified_at' => now()]);

    $response = $this->get($url);

    expect($response->getStatusCode())->toBe(404, $url.' => '.describeResponse($response));
})->with([
    '/raw-materials/abc',
    '/products/abc',
    '/quotations/abc',
    '/warehouses/abc',
    '/formulas/abc',
    '/sales-orders/abc',
    '/production-orders/abc',
    '/production-orders/9223372036854775808',
    '/production-orders/1/packaging-plans/abc/labels',
    '/inventory-movements/abc',
    '/finished-inventory-movements/abc',
]);

it('still resolves a valid id and the literal create routes', function () {
    actingAsRole(SystemRole::Admin, ['email_verified_at' => now()]);
    $product = Product::factory()->create();

    $this->get('/products/'.$product->id)->assertOk();
    $this->get('/products/create')->assertOk();
    $this->get('/production-orders/create')->assertOk();
});
