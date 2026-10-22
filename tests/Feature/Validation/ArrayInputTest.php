<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\User;

/*
 * Auditoría 3.6, PR 10 (INV-07, PT-07, AUTH-01): un arreglo donde va un texto no debe dar 500. Las reglas básicas
 * (`string`, `in`) lo rechazan, pero los `after()` corren aunque ya haya errores y convertían el arreglo a texto.
 * Solo se alcanza con una petición armada a mano, pero un 500 no es una respuesta de validación.
 */

it('rejects an array as lot number of a raw material movement with 422', function () {
    actingAsRole(SystemRole::Admin, ['email_verified_at' => now()]);

    $response = $this->postJson(route('inventory-movements.store'), [
        'type' => 'entry',
        'lot_number' => ['LOTE-1'],
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['lot_number']);
});

it('rejects an array as type or reason of a finished product movement with 422', function (array $payload, string $field) {
    actingAsRole(SystemRole::Admin, ['email_verified_at' => now()]);

    $response = $this->postJson(route('finished-inventory-movements.store'), $payload);

    $response->assertUnprocessable()->assertJsonValidationErrors([$field]);
})->with([
    'type' => [['type' => ['entry'], 'reason' => 'adjustment'], 'type'],
    'reason' => [['type' => 'entry', 'reason' => ['adjustment']], 'reason'],
]);

it('rejects an array as login email with 422 instead of failing in the rate limiter', function () {
    User::factory()->create(['email' => 'operario@example.com']);

    $response = $this->postJson('/login', [
        'email' => ['operario@example.com'],
        'password' => 'password',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['email']);
});
