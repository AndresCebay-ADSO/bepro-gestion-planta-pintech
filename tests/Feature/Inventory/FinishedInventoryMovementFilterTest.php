<?php

declare(strict_types=1);

use App\Enums\FinishedInventoryMovementReason;
use App\Enums\InventoryMovementType;
use App\Enums\WarehouseType;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('admin');

    $this->warehouseA = Warehouse::create([
        'name' => 'Bodega Central PT',
        'city' => 'Cali',
        'type' => WarehouseType::Factory,
        'is_active' => true,
    ]);

    $this->warehouseB = Warehouse::create([
        'name' => 'Bodega Sucursal Norte',
        'city' => 'Yumbo',
        'type' => WarehouseType::Storage,
        'is_active' => true,
    ]);

    $this->category = ProductCategory::factory()->create();
    $this->uom = UnitOfMeasure::factory()->create(['symbol' => 'gl']);

    $this->productA = Product::factory()->create([
        'code' => 'PT-VINIL-001',
        'name' => 'Vinilo Blanco Super',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->uom->id,
    ]);

    $this->variantA = ProductVariant::factory()->create([
        'product_id' => $this->productA->id,
        'code' => 'VAR-VINIL-GALON',
        'name' => 'Galón Vinilo Blanco',
        'unit_of_measure_id' => $this->uom->id,
    ]);

    $this->productB = Product::factory()->create([
        'code' => 'PT-ESMALTE-002',
        'name' => 'Esmalte Sintético Gris',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->uom->id,
    ]);

    $this->variantB = ProductVariant::factory()->create([
        'product_id' => $this->productB->id,
        'code' => 'VAR-ESMALTE-CUBETA',
        'name' => 'Cubeta Esmalte Gris',
        'unit_of_measure_id' => $this->uom->id,
    ]);

    $this->movementA = FinishedInventoryMovement::create([
        'product_id' => $this->productA->id,
        'product_variant_id' => $this->variantA->id,
        'warehouse_id' => $this->warehouseA->id,
        'type' => InventoryMovementType::Entry,
        'reason' => FinishedInventoryMovementReason::Production,
        'quantity' => 20,
        'movement_date' => '2026-06-01',
        'created_by' => $this->admin->id,
    ]);

    $this->movementB = FinishedInventoryMovement::create([
        'product_id' => $this->productB->id,
        'product_variant_id' => $this->variantB->id,
        'warehouse_id' => $this->warehouseB->id,
        'type' => InventoryMovementType::Exit,
        'reason' => FinishedInventoryMovementReason::Sale,
        'quantity' => 5,
        'movement_date' => '2026-06-15',
        'created_by' => $this->admin->id,
    ]);
});

it('filters by product name or code', function (): void {
    actingAs($this->admin);

    $response = get(route('finished-inventory-movements.index', ['search' => 'VINIL-001']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/FinishedMovements/Index')
            ->has('movements.data', 1)
            ->where('movements.data.0.id', $this->movementA->id)
    );
});

it('filters by product variant code', function (): void {
    actingAs($this->admin);

    $response = get(route('finished-inventory-movements.index', ['search' => 'VAR-ESMALTE']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/FinishedMovements/Index')
            ->has('movements.data', 1)
            ->where('movements.data.0.id', $this->movementB->id)
    );
});

it('filters by movement type', function (): void {
    actingAs($this->admin);

    $response = get(route('finished-inventory-movements.index', ['type' => 'entry']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/FinishedMovements/Index')
            ->has('movements.data', 1)
            ->where('movements.data.0.id', $this->movementA->id)
    );
});

it('filters by movement reason', function (): void {
    actingAs($this->admin);

    $response = get(route('finished-inventory-movements.index', ['reason' => 'sale']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/FinishedMovements/Index')
            ->has('movements.data', 1)
            ->where('movements.data.0.id', $this->movementB->id)
    );
});

it('filters by warehouse_id', function (): void {
    actingAs($this->admin);

    $response = get(route('finished-inventory-movements.index', ['warehouse_id' => $this->warehouseA->id]));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/FinishedMovements/Index')
            ->has('movements.data', 1)
            ->where('movements.data.0.id', $this->movementA->id)
    );
});

it('filters by date range', function (): void {
    actingAs($this->admin);

    $response = get(route('finished-inventory-movements.index', [
        'date_from' => '2026-06-10',
        'date_to' => '2026-06-20',
    ]));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/FinishedMovements/Index')
            ->has('movements.data', 1)
            ->where('movements.data.0.id', $this->movementB->id)
    );
});

it('combines multiple filters correctly', function (): void {
    actingAs($this->admin);

    $response = get(route('finished-inventory-movements.index', [
        'search' => 'VINIL',
        'type' => 'entry',
        'reason' => 'production',
        'warehouse_id' => $this->warehouseA->id,
        'date_from' => '2026-05-01',
        'date_to' => '2026-06-05',
    ]));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/FinishedMovements/Index')
            ->has('movements.data', 1)
            ->where('movements.data.0.id', $this->movementA->id)
    );
});

it('preserves query string in pagination links', function (): void {
    actingAs($this->admin);

    $response = get(route('finished-inventory-movements.index', ['search' => 'VINIL']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/FinishedMovements/Index')
            ->has('movements.data', 1)
            ->has('movements.links')
            ->where('movements.links', fn ($links) => collect($links)->contains(
                fn ($link) => $link['url'] !== null && str_contains((string) $link['url'], 'search=VINIL')
            ))
    );
});

it('ignores unknown filter keys and strips whitespace', function (): void {
    actingAs($this->admin);

    $response = get(route('finished-inventory-movements.index', [
        'search' => '   VINIL-001   ',
        'unknown_key' => 'hack',
    ]));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/FinishedMovements/Index')
            ->has('movements.data', 1)
            ->where('movements.data.0.id', $this->movementA->id)
    );
});

it('fails validation when date_to is before date_from', function (): void {
    actingAs($this->admin);

    $response = getJson(route('finished-inventory-movements.index', [
        'date_from' => '2026-06-20',
        'date_to' => '2026-06-10',
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['date_to']);
});

it('forbids unauthorized users from accessing finished inventory movements index', function (): void {
    $guestUser = User::factory()->create();

    actingAs($guestUser);

    $response = get(route('finished-inventory-movements.index'));

    $response->assertForbidden();
});

/**
 * Un lote de PT de la OP-2026-0042 (lote 1042) con su entrada de producción y una venta. Solo la entrada guarda la OP.
 *
 * @return array{0: FinishedInventoryMovement, 1: FinishedInventoryMovement}
 */
function finishedLotFixture(object $context): array
{
    $order = ProductionOrder::factory()->create([
        'order_number' => 'OP-2026-0042',
        'lot_number' => 1042,
        'product_id' => $context->productA->id,
    ]);
    $batch = FinishedProductBatch::create([
        'product_id' => $context->productA->id,
        'product_variant_id' => $context->variantA->id,
        'production_order_id' => $order->id,
        'initial_quantity' => 10,
        'entry_date' => '2026-06-20',
    ]);
    $movement = fn (array $attributes) => FinishedInventoryMovement::create([
        'product_id' => $context->productA->id,
        'product_variant_id' => $context->variantA->id,
        'warehouse_id' => $context->warehouseA->id,
        'finished_product_batch_id' => $batch->id,
        'created_by' => $context->admin->id,
        ...$attributes,
    ]);

    return [
        $movement(['type' => InventoryMovementType::Entry, 'reason' => FinishedInventoryMovementReason::Production, 'quantity' => 10, 'movement_date' => '2026-06-20', 'production_order_id' => $order->id]),
        $movement(['type' => InventoryMovementType::Exit, 'reason' => FinishedInventoryMovementReason::Sale, 'quantity' => 4, 'movement_date' => '2026-06-21']),
    ];
}

// B55: el lote de PT se busca por el número de lote de su OP y por el número de la OP, a través del lote. Solo las
// entradas guardan la OP: sin pasar por el lote, las salidas no aparecerían.
it('finds the entry and the exits of a lot by its lot number', function (): void {
    actingAs($this->admin);
    [$entry, $exit] = finishedLotFixture($this);

    get(route('finished-inventory-movements.index', ['search' => '1042']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('movements.data', 2)
            ->where('movements.data.0.id', $exit->id)
            ->where('movements.data.0.batch.production_order.lot_number', 1042)
            ->where('movements.data.1.id', $entry->id));
});

it('finds the entry and the exits of a lot by its production order number', function (): void {
    actingAs($this->admin);
    [$entry, $exit] = finishedLotFixture($this);

    get(route('finished-inventory-movements.index', ['search' => 'op-2026-0042']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('movements.data', 2)
            ->where('movements.data.0.id', $exit->id)
            ->where('movements.data.1.id', $entry->id));
});

it('searches the order number and the lot of a batch in a single subquery', function (): void {
    actingAs($this->admin);
    finishedLotFixture($this);

    DB::enableQueryLog();
    get(route('finished-inventory-movements.index', ['search' => '1042']))->assertOk();
    $listing = collect(DB::getQueryLog())
        ->pluck('query')
        ->first(fn (string $sql) => str_contains($sql, 'from "finished_inventory_movements"') && str_contains($sql, 'lot_number'));

    expect(substr_count($listing, 'from "production_orders"'))->toBe(1);
});

it('offers finished batches identified by the lot number of their order', function (): void {
    actingAs($this->admin);
    finishedLotFixture($this);

    get(route('finished-inventory-movements.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->reloadOnly('batches', fn (AssertableInertia $reload) => $reload
                ->where('batches.0.lot_number', 1042)));
});

it('shows the lot number of the order on the movement detail', function (): void {
    actingAs($this->admin);
    [, $exit] = finishedLotFixture($this);

    get(route('finished-inventory-movements.show', $exit))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('movement.batch.production_order.lot_number', 1042));
});

// 3.4: el color de la OP va junto al lote al despachar y en los movimientos.
it('shows the order color next to the lot', function (): void {
    actingAs($this->admin);
    [, $exit] = finishedLotFixture($this);
    ProductionOrder::query()->where('lot_number', 1042)->update(['color' => 'RAL 3020']);

    get(route('finished-inventory-movements.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('movements.data.0.batch.production_order.color', 'RAL 3020')
            ->reloadOnly('batches', fn (AssertableInertia $reload) => $reload->where('batches.0.color', 'RAL 3020')));

    get(route('finished-inventory-movements.show', $exit))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('movement.batch.production_order.color', 'RAL 3020'));
});
