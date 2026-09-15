<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\UnitOfMeasure;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $category = ProductCategory::create(['name' => 'Categoría Show']);
    $unit = UnitOfMeasure::create(['code' => 'GAL-SHOW', 'name' => 'Galón', 'symbol' => 'gal']);

    $this->product = Product::create([
        'code' => 'P-SHOW-001',
        'name' => 'Producto Show',
        'category_id' => $category->id,
        'unit_of_measure_id' => $unit->id,
        'is_active' => true,
        'current_cost' => 100,
        'cif_percentage' => 20,
        'price_threshold' => 5,
        'sales_margin' => 35,
        'current_price' => 150,
    ]);

    $this->variant = ProductVariant::create([
        'product_id' => $this->product->id,
        'code' => 'VAR-SHOW-001',
        'name' => 'Galón',
        'unit_of_measure_id' => $unit->id,
        'presentation_value' => 1,
        'presentation_label' => 'GAL',
        'current_cost' => 100,
        'current_price' => 150,
        'is_active' => true,
    ]);
});

it('hides every cost attribute, including sales_margin, from users without costs.view', function (): void {
    $comercial = userWithRole(SystemRole::Commercial);

    $this->actingAs($comercial)
        ->get(route('products.show', $this->product))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.viewCosts', false)
            ->where('product.name', 'Producto Show')
            ->missing('product.current_cost')
            ->missing('product.cif_percentage')
            ->missing('product.price_threshold')
            ->missing('product.sales_margin')
            ->missing('product.variants.0.current_cost'));
});

it('shows every cost attribute, including sales_margin, to users with costs.view', function (): void {
    $admin = userWithRole(SystemRole::Admin);

    $this->actingAs($admin)
        ->get(route('products.show', $this->product))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.viewCosts', true)
            ->where('product.current_cost', '100.0000')
            ->where('product.sales_margin', '35.00')
            ->where('product.variants.0.current_cost', '100.0000'));
});
