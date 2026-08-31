<?php

declare(strict_types=1);

use App\Enums\ProductionOrderStatus;
use App\Enums\WarehouseType;
use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
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

    $this->warehouse = Warehouse::create([
        'name' => 'Fábrica Cali',
        'city' => 'Cali',
        'type' => WarehouseType::Factory,
        'is_active' => true,
    ]);

    $this->productA = Product::factory()->create([
        'code' => 'PROD-001',
        'name' => 'Pintura Blanca Tipo 1',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->uom->id,
    ]);

    $this->productB = Product::factory()->create([
        'code' => 'PROD-002',
        'name' => 'Esmalte Sintético Negro',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->uom->id,
    ]);

    $this->formulaA = Formula::create([
        'product_id' => $this->productA->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $this->admin->id,
    ]);

    $this->formulaB = Formula::create([
        'product_id' => $this->productB->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $this->admin->id,
    ]);

    $this->orderA = ProductionOrder::create([
        'order_number' => 'OP-2026-001',
        'lot_number' => 101,
        'product_id' => $this->productA->id,
        'formula_id' => $this->formulaA->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 100,
        'status' => ProductionOrderStatus::Pending,
        'planned_date' => '2026-06-01',
        'completion_date' => null,
        'created_by' => $this->admin->id,
    ]);
    $this->orderA->forceFill(['created_at' => Carbon::parse('2026-06-01 10:00:00')])->saveQuietly();

    $this->orderB = ProductionOrder::create([
        'order_number' => 'OP-2026-002',
        'lot_number' => 202,
        'product_id' => $this->productB->id,
        'formula_id' => $this->formulaB->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 200,
        'status' => ProductionOrderStatus::Completed,
        'planned_date' => '2026-06-05',
        'completion_date' => '2026-06-15',
        'created_by' => $this->admin->id,
    ]);
    $this->orderB->forceFill(['created_at' => Carbon::parse('2026-06-10 10:00:00')])->saveQuietly();
});

it('filters by order number', function (): void {
    actingAs($this->admin);

    $response = get(route('production-orders.index', ['search' => 'OP-2026-001']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Production/Orders/Index')
        ->has('orders.data', 1)
        ->where('orders.data.0.id', $this->orderA->id)
    );
});

it('filters by product code', function (): void {
    actingAs($this->admin);

    $response = get(route('production-orders.index', ['search' => 'PROD-001']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Production/Orders/Index')
        ->has('orders.data', 1)
        ->where('orders.data.0.id', $this->orderA->id)
    );
});

it('filters by product name', function (): void {
    actingAs($this->admin);

    $response = get(route('production-orders.index', ['search' => 'Esmalte']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Production/Orders/Index')
        ->has('orders.data', 1)
        ->where('orders.data.0.id', $this->orderB->id)
    );
});

it('filters by lot number numeric search', function (): void {
    actingAs($this->admin);

    $response = get(route('production-orders.index', ['search' => '101']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Production/Orders/Index')
        ->has('orders.data', 1)
        ->where('orders.data.0.id', $this->orderA->id)
    );
});

it('does not match lot number when search contains decimal or scientific notation', function (string $search): void {
    actingAs($this->admin);

    $response = get(route('production-orders.index', ['search' => $search]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Production/Orders/Index')
        ->has('orders.data', 0)
    );
})->with(['101.9', '1e2']);

it('filters by status', function (): void {
    actingAs($this->admin);

    $response = get(route('production-orders.index', ['status' => ProductionOrderStatus::Completed->value]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Production/Orders/Index')
        ->has('orders.data', 1)
        ->where('orders.data.0.id', $this->orderB->id)
    );
});

it('filters by created date from', function (): void {
    actingAs($this->admin);

    $response = get(route('production-orders.index', ['date_from' => '2026-06-08']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Production/Orders/Index')
        ->has('orders.data', 1)
        ->where('orders.data.0.id', $this->orderB->id)
    );
});

it('filters by created date to', function (): void {
    actingAs($this->admin);

    $response = get(route('production-orders.index', ['date_to' => '2026-06-05']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Production/Orders/Index')
        ->has('orders.data', 1)
        ->where('orders.data.0.id', $this->orderA->id)
    );
});

it('filters by created date range', function (): void {
    actingAs($this->admin);

    $response = get(route('production-orders.index', [
        'date_from' => '2026-05-01',
        'date_to' => '2026-06-30',
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Production/Orders/Index')
        ->has('orders.data', 2)
    );
});

it('filters by completion date from', function (): void {
    actingAs($this->admin);

    $response = get(route('production-orders.index', ['completed_from' => '2026-06-10']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Production/Orders/Index')
        ->has('orders.data', 1)
        ->where('orders.data.0.id', $this->orderB->id)
    );
});

it('filters by completion date to', function (): void {
    actingAs($this->admin);

    $response = get(route('production-orders.index', ['completed_to' => '2026-06-10']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Production/Orders/Index')
        ->has('orders.data', 0)
    );
});

it('filters by completion date range', function (): void {
    actingAs($this->admin);

    $response = get(route('production-orders.index', [
        'completed_from' => '2026-06-01',
        'completed_to' => '2026-06-20',
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Production/Orders/Index')
        ->has('orders.data', 1)
        ->where('orders.data.0.id', $this->orderB->id)
    );
});

it('normalizes whitespace in search', function (): void {
    actingAs($this->admin);

    $response = get(route('production-orders.index', ['search' => '   OP-2026-001   ']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Production/Orders/Index')
        ->has('orders.data', 1)
        ->where('orders.data.0.id', $this->orderA->id)
    );
});

it('ignores invalid filter keys', function (): void {
    actingAs($this->admin);

    $response = get(route('production-orders.index', ['invalid_key' => 'value']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Production/Orders/Index')
        ->has('orders.data', 2)
        ->missing('filters.invalid_key')
    );
});

it('preserves query string in pagination', function (): void {
    actingAs($this->admin);

    for ($i = 3; $i <= 20; $i++) {
        ProductionOrder::create([
            'order_number' => sprintf('OP-2026-%03d', $i),
            'lot_number' => 300 + $i,
            'product_id' => $this->productA->id,
            'formula_id' => $this->formulaA->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 50,
            'status' => ProductionOrderStatus::Pending,
            'planned_date' => '2026-06-01',
            'created_by' => $this->admin->id,
        ]);
    }

    $response = get(route('production-orders.index', ['search' => 'PROD-001']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('orders.links.1', fn ($link) => $link
            ->where('active', true)
            ->where('label', '1')
            ->where('url', fn (?string $url) => $url === null || str_contains($url, 'search=PROD-001'))
            ->etc()
        )
        ->has('orders.links.2', fn ($link) => $link
            ->where('active', false)
            ->where('label', '2')
            ->where('url', fn (?string $url) => $url !== null && str_contains($url, 'search=PROD-001'))
            ->etc()
        )
    );
});

it('returns 403 for unauthorized user', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    actingAs($user);

    $response = get(route('production-orders.index'));

    $response->assertForbidden();
});

it('rejects invalid status value', function (): void {
    actingAs($this->admin);

    $response = getJson(route('production-orders.index', ['status' => 'invalid_status']));

    $response->assertJsonValidationErrors(['status']);
});

it('rejects invalid date format', function (): void {
    actingAs($this->admin);

    $response = getJson(route('production-orders.index', ['date_from' => '01-06-2026']));

    $response->assertJsonValidationErrors(['date_from']);
});

it('rejects date_to earlier than date_from', function (): void {
    actingAs($this->admin);

    $response = getJson(route('production-orders.index', [
        'date_from' => '2026-06-15',
        'date_to' => '2026-06-01',
    ]));

    $response->assertJsonValidationErrors(['date_to']);
});

it('rejects completed_to earlier than completed_from', function (): void {
    actingAs($this->admin);

    $response = getJson(route('production-orders.index', [
        'completed_from' => '2026-06-20',
        'completed_to' => '2026-06-10',
    ]));

    $response->assertJsonValidationErrors(['completed_to']);
});
