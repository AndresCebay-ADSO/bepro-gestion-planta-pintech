<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('admin');

    $this->category = ProductCategory::factory()->create(['name' => 'Industrial']);
    $this->uom = UnitOfMeasure::factory()->create(['code' => 'GAL', 'name' => 'Galón', 'symbol' => 'gal']);

    $this->productA = Product::factory()->create([
        'name' => 'Pintura Epoxica',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->uom->id,
    ]);

    $this->productB = Product::factory()->create([
        'name' => 'Sellador Acrílico',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->uom->id,
    ]);

    $this->productC = Product::factory()->create([
        'name' => 'Esmalte Industrial',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->uom->id,
    ]);
});

test('filters products by name search', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('products.index', ['search' => 'Epoxica']));

    $response->assertInertia(fn ($page) => $page
        ->has('products.data', 1)
        ->where('products.data.0.id', $this->productA->id)
        ->where('filters.search', 'Epoxica')
    );
});

test('search is case insensitive', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('products.index', ['search' => 'epoxica']));

    $response->assertInertia(fn ($page) => $page
        ->has('products.data', 1)
        ->where('products.data.0.id', $this->productA->id)
    );
});

// Los nombres se guardan en mayúsculas (PR 8): «epóxica» tiene que encontrar «PINTURA EPÓXICA». El `LOWER()` de
// PostgreSQL cambia las letras con tilde; el de SQLite no, por eso este caso solo corre en PostgreSQL.
test('search with accents finds uppercase names', function (): void {
    Product::factory()->create(['name' => 'Laca Poliuretánica', 'category_id' => $this->category->id, 'unit_of_measure_id' => $this->uom->id]);

    $this->actingAs($this->admin)
        ->get(route('products.index', ['search' => 'poliuretánica']))
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'LACA POLIURETÁNICA')
        );
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'El LOWER() de SQLite no cambia las letras con tilde.');

test('whitespace in search is normalized', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('products.index', ['search' => '  Pintura   Epoxica  ']));

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
    Product::factory()->count(16)->sequence(fn ($sq) => ['name' => "Pintura Extra {$sq->index}"])->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->uom->id,
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('products.index', ['search' => 'Pintura Extra']));

    $response->assertInertia(fn ($page) => $page
        ->has('products.data', 15)
        ->where('products.links.2.url', fn ($url) => is_string($url) && str_contains($url, 'search=Pintura') && str_contains($url, 'page=2'))
    );
});

test('unauthorized users cannot access products index', function (): void {
    $unauthorizedUser = User::factory()->create();

    $this->actingAs($unauthorizedUser)
        ->get(route('products.index'))
        ->assertForbidden();
});
