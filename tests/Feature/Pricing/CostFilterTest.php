<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('admin');

    $this->category = ProductCategory::create(['name' => 'Industrial']);
    $this->uom = UnitOfMeasure::create(['code' => 'GAL', 'name' => 'Galón', 'symbol' => 'gal']);

    $this->productA = Product::create([
        'code' => 'COST-001',
        'name' => 'Pintura Epóxica',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->uom->id,
        'is_active' => true,
    ]);

    $this->productB = Product::create([
        'code' => 'COST-002',
        'name' => 'Sellador Acrílico',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->uom->id,
        'is_active' => true,
    ]);

    $this->productC = Product::create([
        'code' => 'COST-003',
        'name' => 'Esmalte Industrial',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->uom->id,
        'is_active' => true,
    ]);
});

test('filters costs by product name search', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('admin.costs.index', ['search' => 'Epóxica']));

    $response->assertInertia(fn ($page) => $page
        ->has('products.data', 1)
        ->where('products.data.0.id', $this->productA->id)
        ->where('filters.search', 'Epóxica')
    );
});

test('filters costs by product code search', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('admin.costs.index', ['search' => 'COST-002']));

    $response->assertInertia(fn ($page) => $page
        ->has('products.data', 1)
        ->where('products.data.0.id', $this->productB->id)
        ->where('filters.search', 'COST-002')
    );
});

test('search is case insensitive', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('admin.costs.index', ['search' => 'epóxica']));

    $response->assertInertia(fn ($page) => $page
        ->has('products.data', 1)
        ->where('products.data.0.id', $this->productA->id)
    );
});

test('whitespace in search is normalized', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('admin.costs.index', ['search' => '  Pintura   Epóxica  ']));

    $response->assertInertia(fn ($page) => $page
        ->has('products.data', 1)
        ->where('products.data.0.id', $this->productA->id)
    );
});

test('invalid filter keys are ignored', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('admin.costs.index', ['search' => 'Epóxica', 'invalid_key' => 'value']));

    $response->assertInertia(fn ($page) => $page
        ->has('products.data', 1)
        ->where('filters.search', 'Epóxica')
        ->missing('filters.invalid_key')
    );
});

test('only active products are shown in costs', function (): void {
    Product::create([
        'code' => 'COST-004',
        'name' => 'Producto Inactivo',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->uom->id,
        'is_active' => false,
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.costs.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('products.data', 3)
    );
});

test('unauthorized users cannot access costs index', function (): void {
    $unauthorizedUser = User::factory()->create();
    $unauthorizedUser->assignRole('comercial');

    $this->actingAs($unauthorizedUser)
        ->get(route('admin.costs.index'))
        ->assertForbidden();
});
