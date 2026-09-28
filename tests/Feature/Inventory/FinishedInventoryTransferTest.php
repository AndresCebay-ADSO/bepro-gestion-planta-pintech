<?php

declare(strict_types=1);

use App\Enums\FinishedInventoryMovementReason;
use App\Enums\InventoryMovementType;
use App\Models\FinishedInventory;
use App\Models\FinishedInventoryMovement;
use App\Models\FinishedProductBatch;
use App\Models\FinishedProductBatchStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\FinishedInventory\FinishedInventoryMovementService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;

// Traslado de producto terminado entre bodegas (B45). Se permite entre cualquier par de bodegas activas, de cualquier
// tipo (decisión del 2026-09-25): la red de sedes cambia y la regla no se amarra a la de hoy.

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * Un lote de producto terminado con 20 unidades en la bodega de origen.
 *
 * @return array{0: User, 1: FinishedProductBatch, 2: Warehouse, 3: Warehouse}
 */
function finishedTransferFixture(?Warehouse $origin = null, ?Warehouse $destination = null): array
{
    $admin = User::factory()->create()->assignRole('admin');
    $unit = UnitOfMeasure::factory()->create();
    $category = ProductCategory::create(['name' => 'Cat traslado']);

    $product = Product::create([
        'code' => 'PROD-TRASLADO',
        'name' => 'Producto traslado',
        'brand' => 'BEPRO',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    // Con presentación, como los lotes reales: nacen del plan de empaque, cuya presentación es obligatoria.
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

    $batch = FinishedProductBatch::create([
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'initial_quantity' => '20',
        'entry_date' => now(),
    ]);

    $origin ??= Warehouse::factory()->factory()->create();
    $destination ??= Warehouse::factory()->storage()->create();

    app(FinishedInventoryMovementService::class)->registerEntry(
        batchId: $batch->id,
        warehouseId: $origin->id,
        quantity: '20',
        reason: FinishedInventoryMovementReason::Production,
        userId: $admin->id,
    );

    return [$admin, $batch, $origin, $destination];
}

/**
 * Envía el traslado como lo hace el formulario (finished-transfer-movement-form.tsx): tipo `exit`, razón `transfer`.
 *
 * @param  array<string, mixed>  $overrides
 */
function postFinishedTransfer(User $user, FinishedProductBatch $batch, Warehouse $origin, ?Warehouse $destination, string $quantity, array $overrides = []): TestResponse
{
    return actingAs($user)->post(route('finished-inventory-movements.store'), array_merge([
        'finished_product_batch_id' => $batch->id,
        'warehouse_id' => $origin->id,
        'destination_warehouse_id' => $destination?->id,
        'type' => InventoryMovementType::Exit->value,
        'reason' => FinishedInventoryMovementReason::Transfer->value,
        'quantity' => $quantity,
        'movement_date' => now()->toDateString(),
    ], $overrides));
}

function stockOf(FinishedProductBatch $batch, Warehouse $warehouse): ?string
{
    // Por el modelo y no con value(): así se aplica el cast decimal:4 igual en SQLite y en PostgreSQL.
    return FinishedProductBatchStock::query()
        ->where('finished_product_batch_id', $batch->id)
        ->where('warehouse_id', $warehouse->id)
        ->first()
        ?->quantity;
}

it('moves stock of the same batch from origin to destination with an exit and an entry', function () {
    [$admin, $batch, $origin, $destination] = finishedTransferFixture();

    postFinishedTransfer($admin, $batch, $origin, $destination, '7.5', ['notes' => 'Reposición Neiva'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(stockOf($batch, $origin))->toBe('12.5000')
        ->and(stockOf($batch, $destination))->toBe('7.5000');

    $movements = FinishedInventoryMovement::query()
        ->where('reason', FinishedInventoryMovementReason::Transfer)
        ->orderBy('id')
        ->get();

    expect($movements)->toHaveCount(2);

    [$exit, $entry] = [$movements[0], $movements[1]];

    expect($exit->type)->toBe(InventoryMovementType::Exit)
        ->and($exit->warehouse_id)->toBe($origin->id)
        ->and($entry->type)->toBe(InventoryMovementType::Entry)
        ->and($entry->warehouse_id)->toBe($destination->id);

    foreach ($movements as $movement) {
        expect($movement->finished_product_batch_id)->toBe($batch->id)
            ->and($movement->quantity)->toBe('7.5000')
            ->and($movement->created_by)->toBe($admin->id)
            ->and($movement->notes)->toBe('Reposición Neiva');
    }

    // La tabla resumen por bodega que leen las pantallas de inventario también se mueve.
    $cached = fn (Warehouse $warehouse) => FinishedInventory::query()
        ->where('product_id', $batch->product_id)
        ->where('product_variant_id', $batch->product_variant_id)
        ->where('warehouse_id', $warehouse->id)
        ->first()
        ?->quantity;

    expect($cached($origin))->toBe('12.5000')
        ->and($cached($destination))->toBe('7.5000');
});

it('allows transfers between any two active warehouses regardless of their type', function (string $originType, string $destinationType) {
    [$admin, $batch, $origin, $destination] = finishedTransferFixture(
        Warehouse::factory()->{$originType}()->create(),
        Warehouse::factory()->{$destinationType}()->create(),
    );

    postFinishedTransfer($admin, $batch, $origin, $destination, '20')
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(stockOf($batch, $origin))->toBe('0.0000')
        ->and(stockOf($batch, $destination))->toBe('20.0000');
})->with([
    'fábrica a almacén' => ['factory', 'storage'],
    'almacén a fábrica' => ['storage', 'factory'],
    'fábrica a fábrica' => ['factory', 'factory'],
    'almacén a almacén' => ['storage', 'storage'],
]);

it('rejects a transfer without a valid destination and moves nothing', function (Closure $destination, string $message) {
    [$admin, $batch, $origin] = finishedTransferFixture();

    postFinishedTransfer($admin, $batch, $origin, $destination($origin), '5')
        ->assertSessionHasErrors(['destination_warehouse_id' => $message]);

    expect(stockOf($batch, $origin))->toBe('20.0000')
        ->and(FinishedInventoryMovement::query()->where('reason', FinishedInventoryMovementReason::Transfer)->exists())->toBeFalse();
})->with([
    'sin destino' => [fn (Warehouse $origin) => null, 'Debe seleccionar la bodega destino para un traslado.'],
    'destino igual al origen' => [fn (Warehouse $origin) => $origin, 'La bodega destino debe ser diferente a la bodega origen.'],
    'destino inactivo' => [fn (Warehouse $origin) => Warehouse::factory()->inactive()->create(), 'El campo almacén de destino seleccionado no es válido.'],
]);

it('rejects moving more than the batch has at the origin and leaves both warehouses untouched', function () {
    [$admin, $batch, $origin, $destination] = finishedTransferFixture();

    postFinishedTransfer($admin, $batch, $origin, $destination, '20.0001')
        ->assertSessionHasErrors('quantity');

    expect(stockOf($batch, $origin))->toBe('20.0000')
        ->and(stockOf($batch, $destination))->toBeNull()
        ->and(FinishedInventoryMovement::query()->where('reason', FinishedInventoryMovementReason::Transfer)->exists())->toBeFalse();
});

it('rejects a batch that has no stock at the origin', function () {
    [$admin, $batch, , $destination] = finishedTransferFixture();
    $emptyOrigin = Warehouse::factory()->storage()->create();

    postFinishedTransfer($admin, $batch, $emptyOrigin, $destination, '1')
        ->assertSessionHasErrors('finished_product_batch_id');

    expect(stockOf($batch, $destination))->toBeNull();
});
