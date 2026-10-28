<?php

declare(strict_types=1);

use App\Enums\FinishedInventoryMovementReason;
use App\Enums\InventoryMovementType;
use App\Enums\SystemRole;
use App\Support\EnumOptions;

/*
 * Auditoría 3.6, PR 10 (PT-02): el servidor aceptaba una entrada manual con razón «Producción» (+50 unidades sin OP ni
 * costo). Solo la crea completar una orden. Las razones manuales de cada tipo son las que el controlador manda al
 * cajón.
 */

it('does not accept a manual entry with the production reason', function (): void {
    actingAsRole(SystemRole::Admin, ['email_verified_at' => now()]);

    $this->postJson(route('finished-inventory-movements.store'), [
        'type' => 'entry',
        'reason' => 'production',
    ])->assertJsonValidationErrors(['reason']);
});

it('keeps accepting the reasons the form offers', function (string $type, string $reason): void {
    actingAsRole(SystemRole::Admin, ['email_verified_at' => now()]);

    $this->postJson(route('finished-inventory-movements.store'), ['type' => $type, 'reason' => $reason])
        ->assertJsonMissingValidationErrors(['reason']);
})->with([
    ['entry', 'return'],
    ['entry', 'adjustment'],
    ['exit', 'sale'],
    ['exit', 'sample'],
    ['exit', 'deterioration'],
    ['exit', 'transformation'],
    ['exit', 'adjustment'],
]);

it('does not accept the reasons of the other direction', function (string $type, string $reason): void {
    actingAsRole(SystemRole::Admin, ['email_verified_at' => now()]);

    $this->postJson(route('finished-inventory-movements.store'), ['type' => $type, 'reason' => $reason])
        ->assertJsonValidationErrors(['reason']);
})->with([
    ['entry', 'sale'],
    ['entry', 'deterioration'],
    ['exit', 'return'],
    ['exit', 'production'],
]);

it('accepts a transfer sent as an exit, which is how the form sends it', function (): void {
    actingAsRole(SystemRole::Admin, ['email_verified_at' => now()]);

    $this->postJson(route('finished-inventory-movements.store'), ['type' => 'exit', 'reason' => 'transfer'])
        ->assertJsonMissingValidationErrors(['reason']);
});

it('does not ask for a type when the reason is a transfer', function (): void {
    actingAsRole(SystemRole::Admin, ['email_verified_at' => now()]);

    $this->postJson(route('finished-inventory-movements.store'), ['reason' => 'transfer'])
        ->assertJsonMissingValidationErrors(['type', 'reason']);
});

it('offers in the drawer exactly the reasons the form request accepts', function (string $type, string $prop): void {
    actingAsRole(SystemRole::Admin, ['email_verified_at' => now()]);

    $offered = $this->get(route('finished-inventory-movements.index'))->assertOk()->inertiaProps($prop);

    expect($offered)->toBe(EnumOptions::for(
        FinishedInventoryMovementReason::manualReasonsFor(InventoryMovementType::from($type)),
    ));

    $offeredValues = array_column($offered, 'value');

    // El traslado tiene su propio cajón (se envía como salida con destino): no es una razón que se elija.
    foreach (FinishedInventoryMovementReason::cases() as $reason) {
        if ($reason === FinishedInventoryMovementReason::Transfer) {
            continue;
        }

        $response = $this->postJson(route('finished-inventory-movements.store'), [
            'type' => $type,
            'reason' => $reason->value,
        ]);

        in_array($reason->value, $offeredValues, true)
            ? $response->assertJsonMissingValidationErrors(['reason'])
            : $response->assertJsonValidationErrors(['reason']);
    }
})->with([
    'entrada' => ['entry', 'entryReasonOptions'],
    'salida' => ['exit', 'exitReasonOptions'],
]);
