<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'produccion']);
    Role::firstOrCreate(['name' => 'comercial']);

    $this->unit = UnitOfMeasure::create([
        'code' => 'gl',
        'name' => 'Galón',
        'symbol' => 'gl',
    ]);

    $this->category = ProductCategory::create([
        'name' => 'Categoría Test',
    ]);

    $this->adminUser = User::factory()->create(['email_verified_at' => now()]);
    $this->adminUser->assignRole('admin');

    $this->productionUser = User::factory()->create(['email_verified_at' => now()]);
    $this->productionUser->assignRole('produccion');

    $this->comercialUser = User::factory()->create(['email_verified_at' => now()]);
    $this->comercialUser->assignRole('comercial');

    $this->product = Product::create([
        'code' => 'PROD-001',
        'name' => 'Producto Test',
        'brand' => 'BEPRO',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'current_cost' => 80.0000,
        'cif_percentage' => 15.00,
        'current_price' => 92.0000,
        'sales_margin' => 25.00,
        'is_active' => true,
    ]);

    $rmCategory = RawMaterialCategory::create([
        'code' => 'ENV-METAL',
        'name' => 'Envase Metal',
        'is_active' => true,
    ]);

    $rawMaterial = RawMaterial::create([
        'code' => 'ENV-001',
        'category_id' => $rmCategory->id,
        'unit_of_measure_id' => $this->unit->id,
        'current_price' => 5.00,
        'minimum_stock' => 1,
        'alert_days_before_expiry' => 30,
        'is_active' => true,
    ]);

    $this->variant = ProductVariant::create([
        'product_id' => $this->product->id,
        'code' => 'VAR-001',
        'name' => 'Cubeta 5 Galones',
        'unit_of_measure_id' => $this->unit->id,
        'presentation_value' => 5.00,
        'presentation_label' => 'Cubeta',
        'current_cost' => 400.0000,
        'current_price' => 460.0000,
        'package_raw_material_id' => $rawMaterial->id,
        'is_active' => true,
    ]);
});

it('allows admin to view price list with variants and sales prices', function () {
    $this->actingAs($this->adminUser)
        ->get(route('prices.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Prices/Index')
            ->has('products.data', 1)
            ->where('products.data.0.id', $this->product->id)
            ->where('products.data.0.name', 'Producto Test')
            ->where('products.data.0.sales_margin', '25.00')
            ->where('products.data.0.sales_price', 122.6667)
            ->has('products.data.0.variants', 1)
            ->where('products.data.0.variants.0.id', $this->variant->id)
            ->where('products.data.0.variants.0.name', 'Cubeta 5 Galones')
            ->where('products.data.0.variants.0.sales_price', 613.3333)
            ->where('can.view_costs', true)
            ->where('can.view_prices', true)
        );
});

it('allows comercial to view price list without cost data', function () {
    $this->actingAs($this->comercialUser)
        ->get(route('prices.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Prices/Index')
            ->has('products.data', 1)
            ->where('can.view_costs', false)
            ->where('can.view_prices', true)
            ->where('products.data.0.id', $this->product->id)
            ->where('products.data.0.sales_price', 122.6667)
            ->has('products.data.0.variants', 1)
            ->where('products.data.0.variants.0.sales_price', 613.3333)
        );
});

it('forbids produccion to view price list', function () {
    $this->actingAs($this->productionUser)
        ->get(route('prices.index'))
        ->assertForbidden();
});

it('requires authentication', function () {
    $this->get(route('prices.index'))
        ->assertRedirect(route('login'));
});

it('calculates sales_price correctly when sales_margin is null', function () {
    $this->product->update(['sales_margin' => null]);

    $this->actingAs($this->adminUser)
        ->get(route('prices.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('products.data.0.sales_price', 92)
            ->where('products.data.0.variants.0.sales_price', 460)
        );
});

it('filters by search term', function () {
    Product::create([
        'code' => 'PROD-002',
        'name' => 'Otro Producto',
        'brand' => 'BEPRO',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'is_active' => true,
    ]);

    $this->actingAs($this->adminUser)
        ->get(route('prices.index', ['search' => 'Cubeta']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $this->product->id)
        );
});
