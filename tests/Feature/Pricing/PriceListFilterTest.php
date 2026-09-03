<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
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

    $this->production = User::factory()->create(['email_verified_at' => now()]);
    $this->production->assignRole('produccion');

    $this->unit = UnitOfMeasure::create([
        'code' => 'gl',
        'name' => 'Galón',
        'symbol' => 'gl',
    ]);

    $this->category = ProductCategory::create([
        'name' => 'Línea Industrial',
    ]);

    $rmCategory = RawMaterialCategory::create([
        'code' => 'ENV-METAL',
        'name' => 'Envase Metal',
        'is_active' => true,
    ]);

    $this->rmEnvase = RawMaterial::create([
        'code' => 'ENV-CAN-01',
        'category_id' => $rmCategory->id,
        'unit_of_measure_id' => $this->unit->id,
        'current_price' => 5.0,
        'minimum_stock' => 10,
        'alert_days_before_expiry' => 30,
        'tracks_inventory' => true,
        'is_active' => true,
    ]);

    $this->productA = Product::create([
        'code' => 'PROD-EPOXY-A',
        'name' => 'Esmalte Epóxico Gris',
        'brand' => 'PINTECH',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'current_cost' => 80.0000,
        'cif_percentage' => 15.00,
        'current_price' => 92.0000,
        'sales_margin' => 25.00,
        'is_active' => true,
    ]);

    $this->variantA = ProductVariant::create([
        'product_id' => $this->productA->id,
        'code' => 'VAR-EPOXY-GALON',
        'name' => 'Galón Epóxico',
        'unit_of_measure_id' => $this->unit->id,
        'presentation_value' => 1,
        'presentation_label' => 'Galón',
        'current_cost' => 80.0,
        'current_price' => 115.0,
        'package_raw_material_id' => $this->rmEnvase->id,
        'is_active' => true,
    ]);

    $this->productB = Product::create([
        'code' => 'PROD-ACRYLIC-B',
        'name' => 'Laca Acrílica Brillante',
        'brand' => 'PINTECH',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'current_cost' => 60.0000,
        'cif_percentage' => 10.00,
        'current_price' => 66.0000,
        'sales_margin' => 20.00,
        'is_active' => true,
    ]);

    $this->variantB = ProductVariant::create([
        'product_id' => $this->productB->id,
        'code' => 'VAR-ACRYLIC-CUBETA',
        'name' => 'Cubeta Acrílica',
        'unit_of_measure_id' => $this->unit->id,
        'presentation_value' => 5,
        'presentation_label' => 'Cubeta',
        'current_cost' => 300.0,
        'current_price' => 396.0,
        'package_raw_material_id' => $this->rmEnvase->id,
        'is_active' => true,
    ]);
});

it('filters by product name', function (): void {
    actingAs($this->admin);

    $response = get(route('prices.index', ['search' => 'Epóxico']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Prices/Index')
            ->has('products.data', 1)
            ->where('products.data.0.id', $this->productA->id)
    );
});

it('filters by product code', function (): void {
    actingAs($this->admin);

    $response = get(route('prices.index', ['search' => 'ACRYLIC-B']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Prices/Index')
            ->has('products.data', 1)
            ->where('products.data.0.id', $this->productB->id)
    );
});

it('filters by variant code or presentation label', function (): void {
    actingAs($this->admin);

    $response = get(route('prices.index', ['search' => 'Cubeta']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Prices/Index')
            ->has('products.data', 1)
            ->where('products.data.0.id', $this->productB->id)
    );
});

it('preserves query string in pagination links', function (): void {
    actingAs($this->admin);

    $response = get(route('prices.index', ['search' => 'PINTECH']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Prices/Index')
            ->has('products.links')
            ->where('products.links', fn ($links) => collect($links)->contains(
                fn ($link) => $link['url'] !== null && str_contains((string) $link['url'], 'search=PINTECH')
            ))
    );
});

it('ignores unknown filter keys and strips whitespace', function (): void {
    actingAs($this->admin);

    $response = get(route('prices.index', [
        'search' => '   Epóxico   ',
        'other' => 'ignored',
    ]));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Prices/Index')
            ->has('products.data', 1)
            ->where('products.data.0.id', $this->productA->id)
    );
});

it('respects role visibility for costs', function (): void {
    actingAs($this->comercial);

    $response = get(route('prices.index'));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Prices/Index')
            ->where('can.view_costs', false)
            ->missing('products.data.0.current_cost')
    );
});

it('forbids unauthorized users from accessing price list', function (): void {
    actingAs($this->production);

    $response = get(route('prices.index'));

    $response->assertForbidden();
});
