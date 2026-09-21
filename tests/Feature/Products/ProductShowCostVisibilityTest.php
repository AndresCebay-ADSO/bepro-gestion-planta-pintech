<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

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

it('hides every cost attribute, including sales_margin and the internal price, from users without costs.view', function (SystemRole $role): void {
    // current_price es el precio interno (costo × (1 + CIF %)), no el de venta.
    $this->actingAs(userWithRole($role))
        ->get(route('products.show', $this->product))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.viewCosts', false)
            ->where('product.name', 'Producto Show')
            ->where('product.variants.0.name', 'Galón')
            ->missing('product.current_cost')
            ->missing('product.current_price')
            ->missing('product.cif_percentage')
            ->missing('product.price_threshold')
            ->missing('product.sales_margin')
            ->missing('product.variants.0.current_cost')
            ->missing('product.variants.0.current_price'));
})->with([SystemRole::Commercial, SystemRole::Production]);

it('shows every cost attribute, including sales_margin and the internal price, to users with costs.view', function (): void {
    $admin = userWithRole(SystemRole::Admin);

    $this->actingAs($admin)
        ->get(route('products.show', $this->product))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.viewCosts', true)
            ->where('product.current_cost', '100.0000')
            ->where('product.current_price', '150.0000')
            ->where('product.sales_margin', '35.00')
            ->where('product.variants.0.current_cost', '100.0000')
            ->where('product.variants.0.current_price', '150.0000'));
});

it('does not send the internal price in the product list to users without costs.view', function (SystemRole $role): void {
    $this->actingAs(userWithRole($role))
        ->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Products/Index')
            ->where('products.data.0.name', 'Producto Show')
            ->missing('products.data.0.current_price'));
})->with([SystemRole::Commercial, SystemRole::Production]);

it('sends the internal price in the product list to users with costs.view', function (): void {
    $this->actingAs(userWithRole(SystemRole::Admin))
        ->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.data.0.current_price', '150.0000'));
});

it('hides cost amounts from the edit form for a product editor without costs.view', function (): void {
    // Un rol creado desde la pantalla de roles (2.4) podría editar productos sin ver costos.
    $role = Role::create(['name' => 'catalogo-sin-costos', 'guard_name' => 'web']);
    $role->syncPermissions([Permission::ProductsView->value, Permission::ProductsEdit->value]);

    $editor = User::factory()->create();
    $editor->assignRole($role);

    $this->actingAs($editor)
        ->get(route('products.edit', $this->product))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Products/Edit')
            ->where('can.managePrices', false)
            ->where('can.viewCosts', false)
            ->where('product.name', 'Producto Show')
            ->missing('product.cif_percentage')
            ->missing('product.price_threshold')
            ->missing('product.current_cost')
            ->missing('product.current_price')
            ->missing('product.sales_margin'));
});

it('sends cost amounts to the edit form for users with costs.view', function (): void {
    $this->actingAs(userWithRole(SystemRole::Admin))
        ->get(route('products.edit', $this->product))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.current_cost', '100.0000')
            ->where('product.current_price', '150.0000')
            ->where('can.viewCosts', true)
            ->where('product.cif_percentage', '20.00')
            ->where('product.price_threshold', '5.00')
            // El formulario no usa el margen: el array explícito no lo envía a nadie.
            ->missing('product.sales_margin'));
});

it('sends only an explicit list of product and variant fields to users without costs.view', function (): void {
    // Arrays explícitos (B22): una columna nueva del modelo no llega a la ficha sin añadirla a propósito.
    $this->actingAs(userWithRole(SystemRole::Commercial))
        ->get(route('products.show', $this->product))
        ->assertInertia(fn (Assert $page) => $page
            ->where('product', fn ($product) => collect($product)->keys()->sort()->values()->all() === collect([
                'id', 'code', 'name', 'brand', 'description', 'is_active', 'category', 'unit_of_measure',
                'quality_viscosity_lower', 'quality_viscosity_upper', 'quality_fineness_lower', 'quality_fineness_upper',
                'quality_solids_lower', 'quality_solids_upper', 'variants', 'product_documents',
            ])->sort()->values()->all())
            ->where('product.variants.0', fn ($variant) => collect($variant)->keys()->sort()->values()->all() === collect([
                'id', 'code', 'name', 'unit_of_measure_id', 'presentation_value', 'presentation_label',
                'package_raw_material_id', 'is_active', 'unit_of_measure',
            ])->sort()->values()->all()));
});
