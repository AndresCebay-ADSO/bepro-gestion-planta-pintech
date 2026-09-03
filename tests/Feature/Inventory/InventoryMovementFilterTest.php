<?php

declare(strict_types=1);

use App\Enums\InventoryMovementType;
use App\Enums\WarehouseType;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\RawMaterial;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('admin');

    $this->warehouseA = Warehouse::create([
        'name' => 'Bodega Central',
        'city' => 'Cali',
        'type' => WarehouseType::Factory,
        'is_active' => true,
    ]);

    $this->warehouseB = Warehouse::create([
        'name' => 'Bodega Norte',
        'city' => 'Yumbo',
        'type' => WarehouseType::Storage,
        'is_active' => true,
    ]);

    $this->rmA = RawMaterial::factory()->create([
        'code' => 'RM-PIGMENT-BLUE',
    ]);

    $this->rmB = RawMaterial::factory()->create([
        'code' => 'RM-RESIN-EPOXY',
    ]);

    $this->batchA = InventoryBatch::factory()->create([
        'raw_material_id' => $this->rmA->id,
        'warehouse_id' => $this->warehouseA->id,
        'lot_number' => 'LOT-BLUE-100',
    ]);

    $this->movementA = InventoryMovement::create([
        'raw_material_id' => $this->rmA->id,
        'warehouse_id' => $this->warehouseA->id,
        'batch_id' => $this->batchA->id,
        'type' => InventoryMovementType::Entry,
        'quantity' => 100,
        'cost_price' => 25.50,
        'movement_date' => '2026-06-01',
        'created_by' => $this->admin->id,
    ]);

    $this->movementB = InventoryMovement::create([
        'raw_material_id' => $this->rmB->id,
        'warehouse_id' => $this->warehouseB->id,
        'batch_id' => null,
        'type' => InventoryMovementType::Exit,
        'quantity' => 50,
        'cost_price' => 40.00,
        'movement_date' => '2026-06-15',
        'created_by' => $this->admin->id,
    ]);
});

it('filters by raw material code search', function (): void {
    actingAs($this->admin);

    $response = get(route('inventory-movements.index', ['search' => 'PIGMENT-BLUE']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/Movements/Index')
            ->has('movements.data', 1)
            ->where('movements.data.0.id', $this->movementA->id)
    );
});

it('filters by batch lot number search', function (): void {
    actingAs($this->admin);

    $response = get(route('inventory-movements.index', ['search' => 'LOT-BLUE-100']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/Movements/Index')
            ->has('movements.data', 1)
            ->where('movements.data.0.id', $this->movementA->id)
    );
});

it('filters by movement type', function (): void {
    actingAs($this->admin);

    $response = get(route('inventory-movements.index', ['type' => 'exit']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/Movements/Index')
            ->has('movements.data', 1)
            ->where('movements.data.0.id', $this->movementB->id)
    );
});

it('filters by warehouse_id', function (): void {
    actingAs($this->admin);

    $response = get(route('inventory-movements.index', ['warehouse_id' => $this->warehouseA->id]));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/Movements/Index')
            ->has('movements.data', 1)
            ->where('movements.data.0.id', $this->movementA->id)
    );
});

it('filters by date range', function (): void {
    actingAs($this->admin);

    $response = get(route('inventory-movements.index', [
        'date_from' => '2026-06-10',
        'date_to' => '2026-06-20',
    ]));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/Movements/Index')
            ->has('movements.data', 1)
            ->where('movements.data.0.id', $this->movementB->id)
    );
});

it('combines multiple filters correctly', function (): void {
    actingAs($this->admin);

    $response = get(route('inventory-movements.index', [
        'search' => 'PIGMENT',
        'type' => 'entry',
        'warehouse_id' => $this->warehouseA->id,
        'date_from' => '2026-05-01',
        'date_to' => '2026-06-05',
    ]));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/Movements/Index')
            ->has('movements.data', 1)
            ->where('movements.data.0.id', $this->movementA->id)
    );
});

it('ignores unknown filter keys and strips whitespace', function (): void {
    actingAs($this->admin);

    $response = get(route('inventory-movements.index', [
        'search' => '   PIGMENT-BLUE   ',
        'unknown_key' => 'malicious_data',
    ]));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/Movements/Index')
            ->has('movements.data', 1)
            ->where('movements.data.0.id', $this->movementA->id)
    );
});

it('fails validation when date_to is before date_from', function (): void {
    actingAs($this->admin);

    $response = getJson(route('inventory-movements.index', [
        'date_from' => '2026-06-20',
        'date_to' => '2026-06-10',
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['date_to']);
});

it('preserves query string in pagination links', function (): void {
    actingAs($this->admin);

    $response = get(route('inventory-movements.index', ['search' => 'PIGMENT-BLUE']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/Movements/Index')
            ->has('movements.data', 1)
            ->has('movements.links')
    );
});

it('forbids unauthorized users from accessing inventory movements index', function (): void {
    $guestUser = User::factory()->create();

    actingAs($guestUser);

    $response = get(route('inventory-movements.index'));

    $response->assertForbidden();
});
