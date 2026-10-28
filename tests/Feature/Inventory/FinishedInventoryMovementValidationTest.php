<?php

declare(strict_types=1);

use App\Enums\FinishedInventoryMovementReason;
use App\Enums\InventoryMovementType;
use App\Models\FinishedInventoryMovement;
use App\Models\FinishedProductBatch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\ProductVariant;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function createFinishedMovementFixture(string $code = 'PROD-RET'): array
{
    $admin = User::factory()->create()->assignRole('admin');
    $unit = UnitOfMeasure::factory()->create();
    $category = ProductCategory::create(['name' => "Cat {$code}"]);

    $product = Product::create([
        'name' => "Producto {$code}",
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    $batch = FinishedProductBatch::create([
        'product_id' => $product->id,
        'production_order_id' => ProductionOrder::factory()->create(['product_id' => $product->id])->id,
        'product_variant_id' => ProductVariant::factory()->create(['product_id' => $product->id])->id,
        'initial_quantity' => '20',
        // Fecha fija en el pasado: con `now()` (UTC), de 19:00 a 24:00 en Bogotá el lote entraría «mañana» y el
        // movimiento de hoy en la planta quedaría antes de su entrada.
        'entry_date' => '2026-09-01',
    ]);

    return [$admin, $product, $batch];
}

function registerFinishedMovement(
    User $admin,
    FinishedProductBatch $batch,
    Warehouse $warehouse,
    string $type,
    string $reason,
    string $quantity,
): void {
    actingAs($admin)
        ->post(route('finished-inventory-movements.store'), [
            'finished_product_batch_id' => $batch->id,
            'warehouse_id' => $warehouse->id,
            'type' => $type,
            'reason' => $reason,
            'quantity' => $quantity,
            'movement_date' => plantToday(),
        ])
        ->assertRedirect();
}

it('does not expose edit update or delete routes for finished inventory movements', function () {
    [$admin, $product, $batch] = createFinishedMovementFixture('PROD-IMMUTABLE');
    $warehouse = Warehouse::factory()->create();

    $movement = FinishedInventoryMovement::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'finished_product_batch_id' => $batch->id,
        'type' => InventoryMovementType::Entry,
        'reason' => FinishedInventoryMovementReason::Adjustment,
        'quantity' => '10',
        'movement_date' => now(),
        'created_by' => $admin->id,
    ]);

    actingAs($admin)
        ->get("/finished-inventory-movements/{$movement->id}/edit")
        ->assertNotFound();

    actingAs($admin)
        ->put("/finished-inventory-movements/{$movement->id}", [])
        ->assertMethodNotAllowed();

    actingAs($admin)
        ->delete("/finished-inventory-movements/{$movement->id}")
        ->assertMethodNotAllowed();
});

it('denies update and delete abilities even for admins', function () {
    [$admin, $product, $batch] = createFinishedMovementFixture('PROD-POLICY');
    $warehouse = Warehouse::factory()->create();

    $movement = FinishedInventoryMovement::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'finished_product_batch_id' => $batch->id,
        'type' => InventoryMovementType::Entry,
        'reason' => FinishedInventoryMovementReason::Adjustment,
        'quantity' => '10',
        'movement_date' => now(),
        'created_by' => $admin->id,
    ]);

    expect(Gate::forUser($admin)->allows('update', $movement))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('delete', $movement))->toBeFalse();
});

it('allows return up to the exited quantity of the batch in the same warehouse', function () {
    [$admin, , $batch] = createFinishedMovementFixture('PROD-RETURN');
    $warehouse = Warehouse::factory()->create();

    registerFinishedMovement($admin, $batch, $warehouse, 'entry', 'adjustment', '10');
    registerFinishedMovement($admin, $batch, $warehouse, 'exit', 'sale', '5');
    registerFinishedMovement($admin, $batch, $warehouse, 'entry', 'return', '5');

    actingAs($admin)
        ->post(route('finished-inventory-movements.store'), [
            'finished_product_batch_id' => $batch->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'entry',
            'reason' => 'return',
            'quantity' => '1',
            'movement_date' => plantToday(),
        ])
        ->assertInvalid(['quantity']);
});

it('blocks return in a warehouse that never had exits for the batch', function () {
    [$admin, , $batch] = createFinishedMovementFixture('PROD-CROSS');
    $warehouseA = Warehouse::factory()->create();
    $warehouseB = Warehouse::factory()->create();

    registerFinishedMovement($admin, $batch, $warehouseA, 'entry', 'adjustment', '10');
    registerFinishedMovement($admin, $batch, $warehouseA, 'exit', 'sale', '5');

    actingAs($admin)
        ->post(route('finished-inventory-movements.store'), [
            'finished_product_batch_id' => $batch->id,
            'warehouse_id' => $warehouseB->id,
            'type' => 'entry',
            'reason' => 'return',
            'quantity' => '5',
            'movement_date' => plantToday(),
        ])
        ->assertInvalid(['quantity']);
});

it('keeps returnable quotas independent per warehouse', function () {
    [$admin, , $batch] = createFinishedMovementFixture('PROD-INDEPENDENT');
    $warehouseA = Warehouse::factory()->create();
    $warehouseB = Warehouse::factory()->create();

    registerFinishedMovement($admin, $batch, $warehouseA, 'entry', 'adjustment', '10');
    registerFinishedMovement($admin, $batch, $warehouseB, 'entry', 'adjustment', '10');
    registerFinishedMovement($admin, $batch, $warehouseA, 'exit', 'sale', '5');
    registerFinishedMovement($admin, $batch, $warehouseB, 'exit', 'sale', '3');
    registerFinishedMovement($admin, $batch, $warehouseA, 'entry', 'return', '5');
    registerFinishedMovement($admin, $batch, $warehouseB, 'entry', 'return', '3');

    actingAs($admin)
        ->post(route('finished-inventory-movements.store'), [
            'finished_product_batch_id' => $batch->id,
            'warehouse_id' => $warehouseA->id,
            'type' => 'entry',
            'reason' => 'return',
            'quantity' => '1',
            'movement_date' => plantToday(),
        ])
        ->assertInvalid(['quantity']);

    actingAs($admin)
        ->post(route('finished-inventory-movements.store'), [
            'finished_product_batch_id' => $batch->id,
            'warehouse_id' => $warehouseB->id,
            'type' => 'entry',
            'reason' => 'return',
            'quantity' => '1',
            'movement_date' => plantToday(),
        ])
        ->assertInvalid(['quantity']);
});

it('rejects entries into an inactive product but still allows exits of its remaining stock', function () {
    [$admin, $product, $batch] = createFinishedMovementFixture('PROD-INACTIVE');
    $warehouse = Warehouse::factory()->create();

    registerFinishedMovement($admin, $batch, $warehouse, 'entry', 'adjustment', '10');
    $product->update(['is_active' => false]);

    $payload = [
        'finished_product_batch_id' => $batch->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => '5',
        'movement_date' => plantToday(),
    ];

    actingAs($admin)
        ->post(route('finished-inventory-movements.store'), [...$payload, 'type' => 'entry', 'reason' => 'adjustment'])
        ->assertSessionHasErrors('finished_product_batch_id');

    actingAs($admin)
        ->post(route('finished-inventory-movements.store'), [...$payload, 'type' => 'exit', 'reason' => 'sale'])
        ->assertSessionHasNoErrors();
});

it('rejects entries into a batch whose variant is inactive', function () {
    [$admin, $product] = createFinishedMovementFixture('PROD-VAR-INACTIVE');
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => false]);
    $batch = FinishedProductBatch::create([
        'product_id' => $product->id,
        'production_order_id' => ProductionOrder::factory()->create(['product_id' => $product->id])->id,
        'product_variant_id' => $variant->id,
        'initial_quantity' => '20',
        // Fecha fija en el pasado: con `now()` (UTC), de 19:00 a 24:00 en Bogotá el lote entraría «mañana» y el
        // movimiento de hoy en la planta quedaría antes de su entrada.
        'entry_date' => '2026-09-01',
    ]);

    actingAs($admin)
        ->post(route('finished-inventory-movements.store'), [
            'finished_product_batch_id' => $batch->id,
            'warehouse_id' => Warehouse::factory()->create()->id,
            'type' => 'entry',
            'reason' => 'adjustment',
            'quantity' => '5',
            'movement_date' => plantToday(),
        ])
        ->assertSessionHasErrors('finished_product_batch_id');
});

// Auditoría 3.6, PR 5: PT-06. La regla limita la cantidad que entra; el saldo del lote más ella también debe caber.
it('rejects an entry that would overflow the batch stock with a field error instead of a server error', function () {
    [$admin, , $batch] = createFinishedMovementFixture('PROD-OVERFLOW');
    $warehouse = Warehouse::factory()->create();
    registerFinishedMovement($admin, $batch, $warehouse, 'entry', 'adjustment', '2147483647');

    actingAs($admin)
        ->post(route('finished-inventory-movements.store'), [
            'finished_product_batch_id' => $batch->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'entry',
            'reason' => 'adjustment',
            'quantity' => '1',
            'movement_date' => plantToday(),
        ])
        ->assertInvalid(['quantity']);

    expect(FinishedInventoryMovement::query()->count())->toBe(1);
});

// §2.11 del plan del PR 5: el `after()` filtraba los saldos con la bodega cruda; «abc» llegaba a la consulta.
it('rejects a warehouse that is not an integer on an exit without reaching the database', function (mixed $warehouseId) {
    [$admin, , $batch] = createFinishedMovementFixture('PROD-WH-ID');
    $warehouse = Warehouse::factory()->create();
    registerFinishedMovement($admin, $batch, $warehouse, 'entry', 'adjustment', '10');

    actingAs($admin)
        ->post(route('finished-inventory-movements.store'), [
            'finished_product_batch_id' => $batch->id,
            'warehouse_id' => $warehouseId,
            'type' => 'exit',
            'reason' => 'sale',
            'quantity' => '1',
            'movement_date' => plantToday(),
        ])
        ->assertInvalid(['warehouse_id']);
})->with(['texto' => ['abc'], 'mayor que un bigint' => ['9223372036854775808']]);

it('rejects a movement dated before its batch entered', function (string $type, string $reason): void {
    [$admin, , $batch] = createFinishedMovementFixture('PROD-FECHA');
    $origin = Warehouse::factory()->create();
    $destination = Warehouse::factory()->create();
    $entryDate = Carbon::parse(plantToday())->subDays(5)->toDateString();
    $batch->update(['entry_date' => $entryDate]);

    // La salida y el traslado necesitan stock en la bodega de origen; la entrada de hoy lo deja.
    if ($type === 'exit') {
        registerFinishedMovement($admin, $batch, $origin, 'entry', 'adjustment', '10');
    }

    $payload = [
        'finished_product_batch_id' => $batch->id,
        'warehouse_id' => $origin->id,
        'destination_warehouse_id' => $reason === 'transfer' ? $destination->id : null,
        'type' => $type,
        'reason' => $reason,
        'quantity' => '1',
    ];

    actingAs($admin)
        ->postJson(route('finished-inventory-movements.store'), [
            ...$payload,
            'movement_date' => Carbon::parse($entryDate)->subDay()->toDateString(),
        ])
        ->assertJsonValidationErrors([
            'movement_date' => __('La fecha de movimiento no puede ser anterior a la entrada del lote (:date).', [
                'date' => Carbon::parse($entryDate)->format('d/m/Y'),
            ]),
        ]);

    // El mismo día en que entró el lote sí vale: el movimiento se registra.
    actingAs($admin)
        ->post(route('finished-inventory-movements.store'), [...$payload, 'movement_date' => $entryDate])
        ->assertSessionHasNoErrors()
        ->assertRedirect();
})->with([
    'entrada' => ['entry', 'adjustment'],
    'salida' => ['exit', 'sale'],
    'traslado' => ['exit', 'transfer'],
]);
