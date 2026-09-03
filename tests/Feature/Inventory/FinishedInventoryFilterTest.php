<?php

declare(strict_types=1);

use App\Models\FinishedInventory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('admin');

    $this->comercial = User::factory()->create(['email_verified_at' => now()]);
    $this->comercial->assignRole('comercial');

    $this->unit = UnitOfMeasure::create([
        'code' => 'und',
        'name' => 'Unidad',
        'symbol' => 'und',
    ]);

    $this->category = ProductCategory::create([
        'name' => 'Categoría PT Test',
    ]);

    $this->productA = Product::create([
        'code' => 'PT-BARNIZ-101',
        'name' => 'Barniz Poliuretano Brillante',
        'brand' => 'PINTECH',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'is_active' => true,
    ]);

    $this->variantA = ProductVariant::create([
        'product_id' => $this->productA->id,
        'code' => 'VAR-BARNIZ-1GL',
        'name' => 'Galón Barniz',
        'unit_of_measure_id' => $this->unit->id,
        'presentation_value' => 1,
        'presentation_label' => 'Galón',
        'is_active' => true,
    ]);

    $this->productB = Product::create([
        'code' => 'PT-ANTICORR-202',
        'name' => 'Anticorrosivo Alquídico Rojo',
        'brand' => 'PINTECH',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'is_active' => true,
    ]);

    $this->variantB = ProductVariant::create([
        'product_id' => $this->productB->id,
        'code' => 'VAR-ANTICORR-5GL',
        'name' => 'Cubeta Anticorrosivo',
        'unit_of_measure_id' => $this->unit->id,
        'presentation_value' => 5,
        'presentation_label' => 'Cubeta',
        'is_active' => true,
    ]);

    $this->warehouseA = Warehouse::factory()->storage()->create(['name' => 'Bodega Central Cali']);
    $this->warehouseB = Warehouse::factory()->storage()->create(['name' => 'Bodega Sucursal Medellin']);

    $this->inventoryA = FinishedInventory::create([
        'product_id' => $this->productA->id,
        'product_variant_id' => $this->variantA->id,
        'warehouse_id' => $this->warehouseA->id,
        'quantity' => 150,
    ]);

    $this->inventoryB = FinishedInventory::create([
        'product_id' => $this->productB->id,
        'product_variant_id' => $this->variantB->id,
        'warehouse_id' => $this->warehouseB->id,
        'quantity' => 75,
    ]);
});

it('filters by product name or code in search', function (): void {
    actingAs($this->admin);

    $response = get(route('finished-inventory.index', ['search' => 'BARNIZ-101']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/FinishedInventory/Index')
            ->has('inventory.data', 1)
            ->where('inventory.data.0.id', $this->inventoryA->id)
    );
});

it('filters by variant name or code in search', function (): void {
    actingAs($this->admin);

    $response = get(route('finished-inventory.index', ['search' => 'ANTICORR-5GL']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/FinishedInventory/Index')
            ->has('inventory.data', 1)
            ->where('inventory.data.0.id', $this->inventoryB->id)
    );
});

it('filters by warehouse_id', function (): void {
    actingAs($this->admin);

    $response = get(route('finished-inventory.index', ['warehouse_id' => $this->warehouseA->id]));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/FinishedInventory/Index')
            ->has('inventory.data', 1)
            ->where('inventory.data.0.id', $this->inventoryA->id)
    );
});

it('filters by product_id and returns product_name in filters prop', function (): void {
    actingAs($this->admin);

    $response = get(route('finished-inventory.index', ['product_id' => $this->productA->id]));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/FinishedInventory/Index')
            ->has('inventory.data', 1)
            ->where('inventory.data.0.id', $this->inventoryA->id)
            ->where('filters.product_name', $this->productA->name)
    );
});

it('combines search and warehouse_id', function (): void {
    actingAs($this->admin);

    $response = get(route('finished-inventory.index', [
        'search' => 'Poliuretano',
        'warehouse_id' => $this->warehouseA->id,
    ]));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/FinishedInventory/Index')
            ->has('inventory.data', 1)
            ->where('inventory.data.0.id', $this->inventoryA->id)
    );
});

it('preserves query string in pagination links', function (): void {
    actingAs($this->admin);

    $response = get(route('finished-inventory.index', ['search' => 'PINTECH']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/FinishedInventory/Index')
            ->has('inventory.links')
    );
});

it('ignores unknown filter keys and strips whitespace', function (): void {
    actingAs($this->admin);

    $response = get(route('finished-inventory.index', [
        'search' => '   BARNIZ   ',
        'foo' => 'bar',
    ]));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/FinishedInventory/Index')
            ->has('inventory.data', 1)
            ->where('inventory.data.0.id', $this->inventoryA->id)
    );
});

it('forbids unauthorized guest user from accessing finished inventory', function (): void {
    $guest = User::factory()->create();

    actingAs($guest);

    $response = get(route('finished-inventory.index'));

    $response->assertForbidden();
});
