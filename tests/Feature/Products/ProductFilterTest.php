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
        'code' => 'PROD-001',
        'name' => 'Pintura Epóxica',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->uom->id,
    ]);

    $this->productB = Product::create([
        'code' => 'PROD-002',
        'name' => 'Sellador Acrílico',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->uom->id,
    ]);

    $this->productC = Product::create([
        'code' => 'PROD-003',
        'name' => 'Esmalte Industrial',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->uom->id,
    ]);
});

test('filters products by name search', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('products.index', ['search' => 'Epóxica']));

    $response->assertInertia(fn ($page) => $page
        ->has('products.data', 1)
        ->where('products.data.0.id', $this->productA->id)
        ->where('filters.search', 'Epóxica')
    );
});

test('filters products by code search', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('products.index', ['search' => 'PROD-002']));

    $response->assertInertia(fn ($page) => $page
        ->has('products.data', 1)
        ->where('products.data.0.id', $this->productB->id)
        ->where('filters.search', 'PROD-002')
    );
});

test('search is case insensitive', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('products.index', ['search' => 'epóxica']));

    $response->assertInertia(fn ($page) => $page
        ->has('products.data', 1)
        ->where('products.data.0.id', $this->productA->id)
    );
});

test('whitespace in search is normalized', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('products.index', ['search' => '  Pintura   Epóxica  ']));

    $response->assertInertia(fn ($page) => $page
        ->has('products.data', 1)
        ->where('products.data.0.id', $this->productA->id)
    );
});

test('invalid filter keys are ignored', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('products.index', ['search' => 'Pintura', 'invalid_key' => 'value']));

    $response->assertInertia(fn ($page) => $page
        ->has('products.data', 1)
        ->where('filters.search', 'Pintura')
        ->missing('filters.invalid_key')
    );
});

test('pagination preserves query string', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('products.index', ['search' => 'PROD']));

    $response->assertInertia(fn ($page) => $page
        ->has('products.data', 3)
        ->has('products.links')
    );
});

test('unauthorized users cannot access products index', function (): void {
    $unauthorizedUser = User::factory()->create();

    $this->actingAs($unauthorizedUser)
        ->get(route('products.index'))
        ->assertForbidden();
});
