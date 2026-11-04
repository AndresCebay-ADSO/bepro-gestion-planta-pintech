<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\RawMaterial;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;

/*
 * El proveedor del lote: se elige del catálogo al crear un lote nuevo, y se consulta en la ficha del movimiento, en el
 * detalle de la materia prima y en la auditoría.
 */

beforeEach(function (): void {
    Queue::fake();
    $this->admin = actingAsRole(SystemRole::Admin, ['email_verified_at' => now()]);

    $this->warehouse = Warehouse::factory()->factory()->create();
    $this->rawMaterial = RawMaterial::factory()->create();
    $this->supplier = Supplier::factory()->create(['name' => 'Químicos del Valle']);
});

/**
 * Una entrada que crea un lote nuevo, con `$overrides` encima.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function newLotEntry(array $overrides = []): array
{
    return array_replace([
        'raw_material_id' => test()->rawMaterial->id,
        'warehouse_id' => test()->warehouse->id,
        'batch_id' => null,
        'type' => 'entry',
        'quantity' => '10',
        'cost_price' => '1500',
        'lot_number' => 'L-PROV-1',
        'supplier_id' => test()->supplier->id,
        'movement_date' => plantToday(),
    ], $overrides);
}

function existingBatch(array $attributes = []): InventoryBatch
{
    return InventoryBatch::factory()->create([
        'raw_material_id' => test()->rawMaterial->id,
        'warehouse_id' => test()->warehouse->id,
        'unit_price' => '1500',
        'initial_quantity' => '100',
        'remaining_quantity' => '100',
        'entry_date' => plantToday(),
        ...$attributes,
    ]);
}

it('saves the supplier in the new lot', function (): void {
    $this->post(route('inventory-movements.store'), newLotEntry())->assertSessionHasNoErrors();

    expect(InventoryBatch::query()->where('lot_number', 'L-PROV-1')->sole()->supplier_id)->toBe($this->supplier->id);
});

it('requires the supplier when an entry creates a new lot, and says so in Spanish', function (): void {
    $this->post(route('inventory-movements.store'), newLotEntry(['supplier_id' => null]))
        ->assertSessionHasErrors(['supplier_id' => 'El campo proveedor es obligatorio.']);

    expect(InventoryBatch::query()->count())->toBe(0);
});

it('rejects an inactive or unknown supplier when an entry creates a new lot', function (Closure $supplierId): void {
    $this->post(route('inventory-movements.store'), newLotEntry(['supplier_id' => $supplierId()]))
        ->assertSessionHasErrors('supplier_id');

    expect(InventoryBatch::query()->count())->toBe(0);
})->with([
    'inactivo' => [fn () => Supplier::factory()->inactive()->create()->id],
    'inexistente' => [fn () => 999999],
    'lista' => [fn () => [1]],
]);

it('does not ask for a supplier when an entry goes to an existing lot', function (): void {
    $batch = existingBatch();

    $this->post(route('inventory-movements.store'), newLotEntry(['batch_id' => $batch->id, 'supplier_id' => null]))
        ->assertSessionHasNoErrors();

    expect($batch->fresh()->remaining_quantity)->toBe('110.0000');
});

it('ignores the supplier sent with an entry to an existing lot, even an inactive one', function (): void {
    $batch = existingBatch(['supplier_id' => $this->supplier->id]);
    $inactive = Supplier::factory()->inactive()->create();

    $this->post(route('inventory-movements.store'), newLotEntry(['batch_id' => $batch->id, 'supplier_id' => $inactive->id]))
        ->assertSessionHasNoErrors();

    expect($batch->fresh()->supplier_id)->toBe($this->supplier->id);
});

it('does not ask for a supplier in an exit', function (): void {
    $batch = existingBatch();

    $this->post(route('inventory-movements.store'), newLotEntry([
        'batch_id' => $batch->id,
        'type' => 'exit',
        'cost_price' => null,
        'supplier_id' => null,
    ]))->assertSessionHasNoErrors();

    expect($batch->fresh()->remaining_quantity)->toBe('90.0000');
});

it('lets the service create a lot without a supplier: only the form requires it', function (): void {
    app(InventoryService::class)->storeMovement(newLotEntry(['supplier_id' => null]), (int) $this->admin->id);

    expect(InventoryBatch::query()->where('lot_number', 'L-PROV-1')->sole()->supplier_id)->toBeNull();
});

it('offers only the active suppliers in the form, with only their id and name', function (): void {
    Supplier::factory()->inactive()->create(['name' => 'Inactivo']);
    Supplier::factory()->create(['name' => 'Alfa', 'nit' => '111']);

    $this->get(route('inventory-movements.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('suppliers')
            ->reloadOnly('suppliers', fn (AssertableInertia $reload) => $reload
                ->where('suppliers', fn ($suppliers) => collect($suppliers)->map(fn ($supplier) => array_keys($supplier))->unique()->values()->all() === [['id', 'name']]
                    && collect($suppliers)->pluck('name')->all() === ['Alfa', 'Químicos del Valle'])));
});

it('sends no suppliers to the form of whoever cannot register movements', function (): void {
    actingAsRole(SystemRole::Production, ['email_verified_at' => now()]);

    $this->get(route('inventory-movements.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->reloadOnly('suppliers', fn (AssertableInertia $reload) => $reload->where('suppliers', [])));
});

it('shows the supplier of the lot in the movement detail, with only its id and name', function (): void {
    $batch = existingBatch(['supplier_id' => $this->supplier->id]);
    $movement = InventoryMovement::query()->create([
        'raw_material_id' => $this->rawMaterial->id,
        'warehouse_id' => $this->warehouse->id,
        'batch_id' => $batch->id,
        'type' => 'exit',
        'quantity' => '1',
        'cost_price' => '1500',
        'movement_date' => plantToday(),
        'created_by' => $this->admin->id,
    ]);

    $this->get(route('inventory-movements.show', $movement))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('movement.batch.supplier', ['id' => $this->supplier->id, 'name' => 'Químicos del Valle']));
});

it('shows the name of the supplier in the raw material detail', function (): void {
    existingBatch(['supplier_id' => $this->supplier->id, 'lot_number' => 'CON-PROV']);

    $this->get(route('raw-materials.show', $this->rawMaterial))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rawMaterial.inventory_batches.0.lot_number', 'CON-PROV')
            ->where('rawMaterial.inventory_batches.0.supplier', 'Químicos del Valle'));
});

it('keeps the raw material detail working for a lot without a supplier', function (): void {
    existingBatch(['supplier_id' => null]);

    $this->get(route('raw-materials.show', $this->rawMaterial))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rawMaterial.inventory_batches.0.supplier', null));
});

it('audits the supplier of the lot', function (): void {
    $this->post(route('inventory-movements.store'), newLotEntry())->assertSessionHasNoErrors();

    $batch = InventoryBatch::query()->where('lot_number', 'L-PROV-1')->sole();
    $activity = Activity::query()
        ->where('subject_type', InventoryBatch::class)
        ->where('subject_id', $batch->id)
        ->where('event', 'created')
        ->sole();

    expect($activity->properties['attributes']['supplier_id'])->toBe($this->supplier->id);
});
