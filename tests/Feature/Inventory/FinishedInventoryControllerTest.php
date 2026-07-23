<?php

declare(strict_types=1);

use App\Models\FinishedInventory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'produccion']);
    Role::firstOrCreate(['name' => 'comercial']);

    $this->unit = UnitOfMeasure::create([
        'code' => 'und',
        'name' => 'Unidad',
        'symbol' => 'und',
    ]);

    $this->category = ProductCategory::create([
        'name' => 'Categoría PT',
    ]);

    $this->product = Product::create([
        'code' => 'PT-001',
        'name' => 'Esmalte PT',
        'brand' => 'BEPRO',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'is_active' => true,
    ]);

    $this->variant = ProductVariant::create([
        'product_id' => $this->product->id,
        'code' => 'PT-001-GAL',
        'name' => 'Galón',
        'unit_of_measure_id' => $this->unit->id,
        'presentation_value' => 1,
        'presentation_label' => 'Galón',
        'is_active' => true,
    ]);

    $this->warehouseVisible = Warehouse::factory()->storage()->create(['name' => 'Bodega Norte']);
    $this->warehouseHidden = Warehouse::factory()->storage()->create(['name' => 'Bodega Sur']);

    FinishedInventory::create([
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouseVisible->id,
        'quantity' => 120,
    ]);

    FinishedInventory::create([
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouseHidden->id,
        'quantity' => 50,
    ]);

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('admin');

    $this->comercial = User::factory()->create(['email_verified_at' => now()]);
    $this->comercial->assignRole('comercial');
    $this->comercial->warehouses()->attach($this->warehouseVisible->id);

    $this->produccion = User::factory()->create(['email_verified_at' => now()]);
    $this->produccion->assignRole('produccion');
});

it('allows admin to view finished inventory index with all stock rows', function (): void {
    $this->actingAs($this->admin)
        ->get(route('finished-inventory.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Inventory/FinishedInventory/Index')
            ->has('inventory.data', 2)
        );
});

it('scopes finished inventory to assigned warehouses for comercial', function (): void {
    $this->actingAs($this->comercial)
        ->get(route('finished-inventory.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('inventory.data', 1)
            ->where('inventory.data.0.quantity', '120.0000')
        );
});

it('forbids unauthenticated access to finished inventory', function (): void {
    $this->get(route('finished-inventory.index'))
        ->assertRedirect(route('login'));
});

it('includes finished inventory on product show', function (): void {
    $this->actingAs($this->comercial)
        ->get(route('products.show', $this->product))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('finishedInventory', 1)
            ->where('finishedInventory.0.quantity', '120.0000')
        );
});

it('includes available stock on price list for comercial', function (): void {
    $this->actingAs($this->comercial)
        ->get(route('prices.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Prices/Index')
            ->has('products.data.0.variants', 1)
            ->where('products.data.0.variants.0.available_stock', 120)
        );
});

it('scopes warehouse filter to accessible warehouses for comercial', function (): void {
    $this->actingAs($this->comercial)
        ->get(route('finished-inventory.index', [
            'warehouse_id' => $this->warehouseHidden->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('inventory.data', 0)
        );
});

it('allows produccion to view finished inventory index', function (): void {
    $this->produccion->warehouses()->attach($this->warehouseVisible->id);

    $this->actingAs($this->produccion)
        ->get(route('finished-inventory.index'))
        ->assertOk();
});
