<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Queue;

/*
 * Auditoría 3.6, PR 10 (INV-13, INV-16): los datos de lote que un movimiento no usa no se validan, y una salida no puede
 * fecharse antes de que su lote hubiera entrado.
 */

beforeEach(function (): void {
    Queue::fake();
    actingAsRole(SystemRole::Admin, ['email_verified_at' => now()]);

    $this->warehouse = Warehouse::factory()->factory()->create();
    $this->rawMaterial = RawMaterial::factory()->create(['unit_of_measure_id' => UnitOfMeasure::factory()->create()->id]);
    $this->batch = InventoryBatch::factory()->create([
        'raw_material_id' => $this->rawMaterial->id,
        'warehouse_id' => $this->warehouse->id,
        'initial_quantity' => '100',
        'remaining_quantity' => '100',
        'unit_price' => '10',
        'entry_date' => '2026-10-10',
    ]);
});

/**
 * @param  array<string, mixed>  $override
 * @return array<string, mixed>
 */
function movementBody(object $test, array $override = []): array
{
    return array_replace([
        'raw_material_id' => $test->rawMaterial->id,
        'warehouse_id' => $test->warehouse->id,
        'batch_id' => $test->batch->id,
        'type' => 'exit',
        'quantity' => '5',
        'movement_date' => plantToday(),
    ], $override);
}

it('does not validate the lot data of an exit, which it never uses (INV-13)', function (): void {
    $this->post(route('inventory-movements.store'), movementBody($this, ['expiry_date' => '2020-01-01', 'lot_number' => str_repeat('X', 80)]))
        ->assertSessionDoesntHaveErrors(['expiry_date', 'lot_number']);
});

it('does not validate the lot data of an entry into an existing lot (INV-13)', function (): void {
    $this->post(route('inventory-movements.store'), movementBody($this, [
        'type' => 'entry',
        'cost_price' => '10',
        'expiry_date' => '2020-01-01',
    ]))->assertSessionDoesntHaveErrors(['expiry_date']);
});

it('still validates the expiry date of an entry that creates a new lot', function (): void {
    $this->post(route('inventory-movements.store'), movementBody($this, [
        'type' => 'entry',
        'batch_id' => null,
        'cost_price' => '10',
        'lot_number' => 'LOTE-NUEVO',
        'expiry_date' => '2020-01-01',
    ]))->assertSessionHasErrors(['expiry_date']);
});

it('rejects an exit dated before its lot entered (INV-16)', function (): void {
    $this->post(route('inventory-movements.store'), movementBody($this, ['movement_date' => '2026-10-09']))
        ->assertSessionHasErrors(['movement_date' => 'La fecha de movimiento no puede ser anterior a la entrada del lote (10/10/2026).']);

    expect(InventoryMovement::query()->count())->toBe(0)
        ->and((string) $this->batch->fresh()->remaining_quantity)->toBe('100.0000');
});

it('accepts an exit on the day the lot entered and after it', function (string $date): void {
    $this->post(route('inventory-movements.store'), movementBody($this, ['movement_date' => $date]))
        ->assertSessionDoesntHaveErrors(['movement_date']);
})->with(['2026-10-10', '2026-10-11', 'today' => fn () => plantToday()]);

it('does not run the lot date check when the date itself is invalid', function (): void {
    $this->post(route('inventory-movements.store'), movementBody($this, ['movement_date' => 'abc']))
        ->assertSessionHasErrors(['movement_date'])
        ->assertSessionDoesntHaveErrors(['batch_id']);
});
