<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\Client;
use App\Models\Product;
use App\Models\Warehouse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Los listados de datos maestros filtran por estado (docs/POLITICA_ELIMINACION.md §5).
 */
it('filtra por estado el listado', function (string $routeName, string $prop, Closure $makeActive, Closure $makeInactive) {
    actingAsRole(SystemRole::Admin);
    $active = $makeActive();
    $inactive = $makeInactive();

    $ids = fn (string $status) => fn (Assert $page) => $page->where("{$prop}.data", function ($rows) use ($active, $inactive, $status) {
        $ids = collect($rows)->pluck('id');

        return match ($status) {
            'active' => $ids->contains($active->id) && ! $ids->contains($inactive->id),
            'inactive' => $ids->contains($inactive->id) && ! $ids->contains($active->id),
            default => $ids->contains($active->id) && $ids->contains($inactive->id),
        };
    });

    foreach (['active', 'inactive', 'all'] as $status) {
        $this->get(route($routeName, ['status' => $status]))->assertOk()->assertInertia($ids($status));
    }
})->with([
    'clientes' => ['clients.index', 'clients', fn () => Client::factory()->create(), fn () => Client::factory()->create(['is_active' => false])],
    'bodegas' => ['warehouses.index', 'warehouses', fn () => Warehouse::factory()->create(), fn () => Warehouse::factory()->create(['is_active' => false])],
    'productos' => ['products.index', 'products', fn () => Product::factory()->create(), fn () => Product::factory()->create(['is_active' => false])],
]);
