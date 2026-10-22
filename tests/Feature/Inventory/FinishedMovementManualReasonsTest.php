<?php

declare(strict_types=1);

use App\Enums\FinishedInventoryMovementReason;
use App\Enums\InventoryMovementType;
use App\Enums\SystemRole;

/*
 * Auditoría 3.6, PR 10 (PT-02): el servidor aceptaba una entrada manual con razón «Producción» (+50 unidades sin OP ni
 * costo). Solo la crea completar una orden. Las razones manuales de cada tipo son las que ofrece el formulario.
 */

/**
 * Los `value` de una lista del front (`entryReasons` o `exitReasons`).
 *
 * @return list<string>
 */
function frontReasonValues(string $constant): array
{
    $source = file_get_contents(resource_path('js/types/finished-inventory.ts'));
    preg_match('/export const '.$constant.': Array<\{.*?\}> = \[(.*?)\];/s', $source, $block);
    preg_match_all("/value: '([a-z_]+)'/", $block[1] ?? '', $values);

    return $values[1];
}

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

it('has the same manual reasons as the front lists', function (): void {
    $php = fn (InventoryMovementType $type): array => array_map(
        fn (FinishedInventoryMovementReason $reason): string => $reason->value,
        FinishedInventoryMovementReason::manualReasonsFor($type),
    );

    expect(frontReasonValues('entryReasons'))->toEqualCanonicalizing($php(InventoryMovementType::Entry))
        ->and(frontReasonValues('exitReasons'))->toEqualCanonicalizing($php(InventoryMovementType::Exit));
});
