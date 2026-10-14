<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

use function Pest\Laravel\actingAs;

/*
 * Auditoría 3.6, INV-14 y PT-01. Los formularios de movimientos viven en un cajón y sus opciones (materias primas,
 * lotes, bodegas) son props opcionales: solo llegan en la recarga parcial que hace el cajón al abrirse. El 422 de un
 * envío inválido es una redirección a la página completa, que llega sin ellas, y el cajón desmontaba el formulario con
 * lo digitado y sin mostrar los errores. Estos tests simulan el protocolo del cliente: las opciones deben ser «once
 * props», para que el cliente conserve su copia en la respuesta del error.
 */

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * Cabeceras de una visita de Inertia hecha por el cliente.
 *
 * @param  array<string, string>  $extra
 * @return array<string, string>
 */
function drawerInertiaHeaders(array $extra = []): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        ...$extra,
    ];
}

dataset('drawer movement forms', [
    'movimientos de MP' => [
        'inventory-movements',
        'Inventory/Movements/Index',
        [
            'rawMaterials' => 'inventory-movements:raw-materials',
            'batches' => 'inventory-movements:batches',
            'warehouses' => 'inventory-movements:warehouses',
        ],
    ],
    'movimientos de PT' => [
        'finished-inventory-movements',
        'Inventory/FinishedMovements/Index',
        [
            'batches' => 'finished-inventory-movements:batches',
            'warehouses' => 'finished-inventory-movements:warehouses',
        ],
    ],
]);

it('keeps the drawer form options on the client when the submission fails validation', function (
    string $routePrefix,
    string $component,
    array $onceKeys,
) {
    $admin = User::factory()->create()->assignRole('admin');
    $props = array_keys($onceKeys);

    // 1. El cajón se abre: recarga parcial que trae las opciones y las declara como «once props».
    $reload = actingAs($admin)
        ->withHeaders(drawerInertiaHeaders([
            'X-Inertia-Partial-Component' => $component,
            'X-Inertia-Partial-Data' => implode(',', $props),
        ]))
        ->get(route("{$routePrefix}.index"));

    $reload->assertOk();
    foreach ($onceKeys as $prop => $key) {
        expect($reload->json('props'))->toHaveKey($prop)
            ->and($reload->json("onceProps.{$key}.prop"))->toBe($prop);
    }

    // 2. Envío vacío: el cliente declara que ya tiene las opciones y sigue la redirección del 422.
    $failed = actingAs($admin)
        ->flushHeaders()
        ->withHeaders(drawerInertiaHeaders([
            'X-Inertia-Except-Once-Props' => implode(',', array_values($onceKeys)),
        ]))
        ->from(route("{$routePrefix}.index"))
        ->followingRedirects()
        ->post(route("{$routePrefix}.store"), []);

    $failed->assertOk();
    expect($failed->json('component'))->toBe($component)
        ->and($failed->json('props.errors'))->toHaveKey('quantity');

    // El servidor no las reenvía, pero las sigue anunciando: así el cliente pone su copia y el formulario no se desmonta.
    foreach ($onceKeys as $prop => $key) {
        expect($failed->json('props'))->not->toHaveKey($prop)
            ->and($failed->json("onceProps.{$key}.prop"))->toBe($prop);
    }
})->with('drawer movement forms');
