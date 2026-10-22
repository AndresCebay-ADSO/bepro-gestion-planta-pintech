<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Http\Middleware\HandleInertiaRequests;
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

// Auditoría 3.6, PR 10 (INV-06, D6): solo la fábrica recibe materia prima. Los almacenes reciben producto terminado.

it('rejects a raw material movement in a warehouse that is not a factory (D6)', function (string $type): void {
    $storage = Warehouse::factory()->storage()->create();

    $this->post(route('inventory-movements.store'), movementBody($this, [
        'type' => $type,
        'batch_id' => null,
        'warehouse_id' => $storage->id,
        'cost_price' => '10',
        'lot_number' => 'LOTE-ALMACEN',
    ]))->assertSessionHasErrors(['warehouse_id' => 'La materia prima solo se registra en la fábrica. Los almacenes reciben producto terminado.']);

    expect(InventoryMovement::query()->count())->toBe(0);
})->with(['entry', 'exit']);

it('still accepts a movement in an active factory', function (): void {
    $this->post(route('inventory-movements.store'), movementBody($this))
        ->assertSessionDoesntHaveErrors(['warehouse_id']);
});

it('offers only the factories in the movement form and lists the lots from the oldest entry first (INV-04)', function (): void {
    $storage = Warehouse::factory()->storage()->create(['name' => 'Almacén Norte']);
    // Entradas en orden inverso al de creación: el id más alto es el lote más antiguo.
    $newest = InventoryBatch::factory()->create([
        'raw_material_id' => $this->rawMaterial->id, 'warehouse_id' => $this->warehouse->id, 'remaining_quantity' => '5',
        'entry_date' => '2026-10-15', 'lot_number' => 'NUEVO',
    ]);
    $oldest = InventoryBatch::factory()->create([
        'raw_material_id' => $this->rawMaterial->id, 'warehouse_id' => $this->warehouse->id, 'remaining_quantity' => '5',
        'entry_date' => '2026-09-01', 'lot_number' => 'VIEJO',
    ]);

    $response = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Inventory/Movements/Index',
        'X-Inertia-Partial-Data' => 'warehouses,batches',
    ])->get(route('inventory-movements.index'));

    $response->assertOk();
    expect(collect($response->json('props.warehouses'))->pluck('id')->all())->toBe([$this->warehouse->id])
        ->and(collect($response->json('props.warehouses'))->pluck('id')->all())->not->toContain($storage->id)
        ->and(collect($response->json('props.batches'))->pluck('lot_number')->all())->toBe(['VIEJO', $this->batch->lot_number, 'NUEVO'])
        ->and($response->json('props.batches.0'))->toHaveKeys(['entry_date', 'expiry_date']);
});
