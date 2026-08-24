<?php

use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('admin');

    $this->category = ProductCategory::factory()->create();
    $this->uom = UnitOfMeasure::factory()->create();

    $this->productA = Product::factory()->create([
        'code' => 'FORM-001',
        'name' => 'Pintura Epóxica',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->uom->id,
    ]);

    $this->productB = Product::factory()->create([
        'code' => 'FORM-002',
        'name' => 'Sellador Acrílico',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->uom->id,
    ]);

    $this->formulaActive = Formula::create([
        'product_id' => $this->productA->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $this->admin->id,
    ]);

    $this->formulaInactive = Formula::create([
        'product_id' => $this->productB->id,
        'version' => 1,
        'is_active' => false,
        'created_by' => $this->admin->id,
    ]);
});

it('filters by product code', function (): void {
    actingAs($this->admin);

    $response = get(route('formulas.index', ['search' => 'FORM-001']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Formulas/Index')
        ->has('formulas.data', 1)
        ->where('formulas.data.0.id', $this->formulaActive->id)
    );
});

it('filters by product name', function (): void {
    actingAs($this->admin);

    $response = get(route('formulas.index', ['search' => 'Epóxica']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Formulas/Index')
        ->has('formulas.data', 1)
        ->where('formulas.data.0.id', $this->formulaActive->id)
    );
});

it('filters by status active', function (): void {
    actingAs($this->admin);

    $response = get(route('formulas.index', ['status' => 'active']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Formulas/Index')
        ->has('formulas.data', 1)
        ->where('formulas.data.0.id', $this->formulaActive->id)
    );
});

it('filters by status inactive', function (): void {
    actingAs($this->admin);

    $response = get(route('formulas.index', ['status' => 'inactive']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Formulas/Index')
        ->has('formulas.data', 1)
        ->where('formulas.data.0.id', $this->formulaInactive->id)
    );
});

it('shows all formulas when status is all or absent', function (): void {
    actingAs($this->admin);

    $response = get(route('formulas.index', ['status' => 'all']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Formulas/Index')
        ->has('formulas.data', 2)
    );

    // Without status param — no filter applied, shows all
    $response = get(route('formulas.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Formulas/Index')
        ->has('formulas.data', 2)
    );
});

it('normalizes whitespace in search', function (): void {
    actingAs($this->admin);

    $response = get(route('formulas.index', ['search' => '   FORM-001  ']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Formulas/Index')
        ->has('formulas.data', 1)
        ->where('formulas.data.0.id', $this->formulaActive->id)
    );
});

it('ignores invalid filter keys', function (): void {
    actingAs($this->admin);

    $response = get(route('formulas.index', ['invalid_key' => 'value']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Formulas/Index')
        ->has('formulas.data', 2)
        ->missing('filters.invalid_key')
    );
});

it('preserves query string in pagination', function (): void {
    actingAs($this->admin);

    for ($i = 0; $i < 15; $i++) {
        Formula::create([
            'product_id' => $this->productA->id,
            'version' => $i + 2,
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);
    }

    $response = get(route('formulas.index', ['search' => 'FORM-001']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('formulas.links.1', fn ($link) => $link
            ->where('active', true)
            ->where('label', '1')
            ->where('url', fn (?string $url) => $url === null || str_contains($url, 'search=FORM-001'))
            ->etc()
        )
        ->has('formulas.links.2', fn ($link) => $link
            ->where('active', false)
            ->where('label', '2')
            ->where('url', fn (?string $url) => $url !== null && str_contains($url, 'search=FORM-001'))
            ->etc()
        )
    );
});

it('returns 403 for unauthorized user', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    actingAs($user);

    $response = get(route('formulas.index'));

    $response->assertForbidden();
});

it('rejects invalid status value', function (): void {
    actingAs($this->admin);

    $response = getJson(route('formulas.index', ['status' => 'xyz']));

    $response->assertJsonValidationErrors(['status']);
});
