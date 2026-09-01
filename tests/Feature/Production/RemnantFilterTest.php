<?php

declare(strict_types=1);

use App\Enums\ProductionOrderStatus;
use App\Enums\RemnantStatus;
use App\Enums\WarehouseType;
use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\ProductionRemnant;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('admin');

    $this->produccion = User::factory()->create(['email_verified_at' => now()]);
    $this->produccion->assignRole('produccion');

    $this->operador = User::factory()->create(['email_verified_at' => now()]);
    $this->operador->assignRole('operador');

    $this->comercial = User::factory()->create(['email_verified_at' => now()]);
    $this->comercial->assignRole('comercial');

    $category = ProductCategory::create(['name' => 'Categoría Pinturas']);
    $uom = UnitOfMeasure::create(['code' => 'gal', 'name' => 'Galón', 'symbol' => 'gal']);

    $this->productA = Product::create([
        'code' => 'PROD-ALFA-001',
        'name' => 'Esmalte Sintético Brillante',
        'category_id' => $category->id,
        'unit_of_measure_id' => $uom->id,
        'current_cost' => 100,
        'cif_percentage' => 10,
        'current_price' => 150,
        'price_threshold' => 5,
    ]);

    $this->productB = Product::create([
        'code' => 'PROD-BETA-002',
        'name' => 'Vinilo Tipo 1 Blanco',
        'category_id' => $category->id,
        'unit_of_measure_id' => $uom->id,
        'current_cost' => 80,
        'cif_percentage' => 10,
        'current_price' => 120,
        'price_threshold' => 5,
    ]);

    $this->warehouseA = Warehouse::create([
        'name' => 'Bodega Principal',
        'city' => 'Bogotá',
        'address' => 'Calle 10 # 20-30',
        'type' => WarehouseType::Factory,
        'is_active' => true,
    ]);

    $this->warehouseB = Warehouse::create([
        'name' => 'Bodega Sucursal',
        'city' => 'Medellín',
        'address' => 'Carrera 45 # 67-89',
        'type' => WarehouseType::Storage,
        'is_active' => true,
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
        'order_number' => 'OP-2026-0001',
        'lot_number' => 101,
        'product_id' => $this->productA->id,
        'formula_id' => $this->formulaA->id,
        'warehouse_id' => $this->warehouseA->id,
        'quantity' => 100,
        'status' => ProductionOrderStatus::Completed,
        'planned_date' => '2026-06-01',
        'created_by' => $this->admin->id,
    ]);

    $this->orderB = ProductionOrder::create([
        'order_number' => 'OP-2026-0002',
        'lot_number' => 102,
        'product_id' => $this->productB->id,
        'formula_id' => $this->formulaB->id,
        'warehouse_id' => $this->warehouseA->id,
        'quantity' => 200,
        'status' => ProductionOrderStatus::Completed,
        'planned_date' => '2026-06-02',
        'created_by' => $this->admin->id,
    ]);

    $this->orderC = ProductionOrder::create([
        'order_number' => 'OP-2026-0003',
        'lot_number' => 103,
        'product_id' => $this->productA->id,
        'formula_id' => $this->formulaA->id,
        'warehouse_id' => $this->warehouseA->id,
        'quantity' => 150,
        'status' => ProductionOrderStatus::Completed,
        'planned_date' => '2026-06-03',
        'created_by' => $this->admin->id,
    ]);

    $this->remnantA = ProductionRemnant::create([
        'source_order_id' => $this->orderA->id,
        'product_id' => $this->productA->id,
        'warehouse_id' => $this->warehouseA->id,
        'original_quantity_gallons' => 20,
        'available_quantity_gallons' => 20,
        'original_quantity_kg' => 100,
        'available_quantity_kg' => 100,
        'density_kg_per_gallon' => 5.0,
        'cost_per_gallon' => 45.0,
        'status' => RemnantStatus::Available,
        'created_by' => $this->admin->id,
    ]);

    $this->remnantB = ProductionRemnant::create([
        'source_order_id' => $this->orderB->id,
        'product_id' => $this->productB->id,
        'warehouse_id' => $this->warehouseB->id,
        'original_quantity_gallons' => 15,
        'available_quantity_gallons' => 5,
        'original_quantity_kg' => 75,
        'available_quantity_kg' => 25,
        'density_kg_per_gallon' => 5.0,
        'cost_per_gallon' => 35.0,
        'status' => RemnantStatus::PartiallyConsumed,
        'created_by' => $this->admin->id,
    ]);

    $this->remnantC = ProductionRemnant::create([
        'source_order_id' => $this->orderC->id,
        'product_id' => $this->productA->id,
        'warehouse_id' => $this->warehouseB->id,
        'original_quantity_gallons' => 10,
        'available_quantity_gallons' => 0,
        'original_quantity_kg' => 50,
        'available_quantity_kg' => 0,
        'density_kg_per_gallon' => 5.0,
        'cost_per_gallon' => 45.0,
        'status' => RemnantStatus::Consumed,
        'created_by' => $this->admin->id,
    ]);
});

it('lists all remnants when no filters are provided', function (): void {
    actingAs($this->admin);

    $response = get(route('production.remnants.index'));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Production/Remnants/Index')
            ->has('remnants.data', 3)
            ->has('statusOptions', 3)
            ->has('warehouseOptions', 2)
    );
});

it('filters remnants by search matching product name', function (): void {
    actingAs($this->admin);

    $response = get(route('production.remnants.index', ['search' => 'Esmalte']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Production/Remnants/Index')
            ->has('remnants.data', 2)
            ->where('remnants.data.0.product_name', 'Esmalte Sintético Brillante')
    );
});

it('filters remnants by search matching product code', function (): void {
    actingAs($this->admin);

    $response = get(route('production.remnants.index', ['search' => 'PROD-BETA-002']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Production/Remnants/Index')
            ->has('remnants.data', 1)
            ->where('remnants.data.0.id', $this->remnantB->id)
    );
});

it('filters remnants by search matching source order number', function (): void {
    actingAs($this->admin);

    $response = get(route('production.remnants.index', ['search' => 'OP-2026-0002']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Production/Remnants/Index')
            ->has('remnants.data', 1)
            ->where('remnants.data.0.id', $this->remnantB->id)
    );
});

it('filters remnants by status', function (): void {
    actingAs($this->admin);

    $response = get(route('production.remnants.index', ['status' => 'partially_consumed']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Production/Remnants/Index')
            ->has('remnants.data', 1)
            ->where('remnants.data.0.id', $this->remnantB->id)
    );
});

it('filters remnants by warehouse_id', function (): void {
    actingAs($this->admin);

    $response = get(route('production.remnants.index', ['warehouse_id' => $this->warehouseA->id]));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Production/Remnants/Index')
            ->has('remnants.data', 1)
            ->where('remnants.data.0.id', $this->remnantA->id)
    );
});

it('combines search, status, and warehouse_id filters', function (): void {
    actingAs($this->admin);

    $response = get(route('production.remnants.index', [
        'search' => 'Esmalte',
        'status' => 'consumed',
        'warehouse_id' => $this->warehouseB->id,
    ]));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Production/Remnants/Index')
            ->has('remnants.data', 1)
            ->where('remnants.data.0.id', $this->remnantC->id)
    );
});

it('normalizes search with extra whitespace', function (): void {
    actingAs($this->admin);

    $response = get(route('production.remnants.index', ['search' => '   Vinilo   Tipo   ']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Production/Remnants/Index')
            ->has('remnants.data', 1)
            ->where('remnants.data.0.id', $this->remnantB->id)
    );
});

it('ignores invalid filter keys', function (): void {
    actingAs($this->admin);

    $response = get(route('production.remnants.index', ['invalid_key' => 'hack']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Production/Remnants/Index')
            ->has('remnants.data', 3)
            ->missing('filters.invalid_key')
    );
});

it('preserves query string in pagination links', function (): void {
    actingAs($this->admin);

    for ($i = 0; $i < 16; $i++) {
        $order = ProductionOrder::create([
            'order_number' => sprintf('OP-PAG-%04d', $i),
            'lot_number' => 500 + $i,
            'product_id' => $this->productA->id,
            'formula_id' => $this->formulaA->id,
            'warehouse_id' => $this->warehouseA->id,
            'quantity' => 50,
            'status' => ProductionOrderStatus::Completed,
            'planned_date' => '2026-06-01',
            'created_by' => $this->admin->id,
        ]);

        ProductionRemnant::create([
            'source_order_id' => $order->id,
            'product_id' => $this->productA->id,
            'warehouse_id' => $this->warehouseA->id,
            'original_quantity_gallons' => 10,
            'available_quantity_gallons' => 10,
            'original_quantity_kg' => 50,
            'available_quantity_kg' => 50,
            'density_kg_per_gallon' => 5.0,
            'cost_per_gallon' => 40.0,
            'status' => RemnantStatus::Available,
            'created_by' => $this->admin->id,
        ]);
    }

    $response = get(route('production.remnants.index', ['search' => 'Esmalte']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Production/Remnants/Index')
            ->has('remnants.links')
            ->where('filters.search', 'Esmalte')
    );
});

it('allows produccion users to access remnants index', function (): void {
    actingAs($this->produccion);
    get(route('production.remnants.index'))->assertOk();
});

it('forbids unauthorized users from accessing remnants index', function (): void {
    actingAs($this->operador);
    get(route('production.remnants.index'))->assertForbidden();

    actingAs($this->comercial);
    get(route('production.remnants.index'))->assertForbidden();

    $guest = User::factory()->create(['email_verified_at' => now()]);
    actingAs($guest);
    get(route('production.remnants.index'))->assertForbidden();
});

it('rejects invalid status value with 422', function (): void {
    actingAs($this->admin);

    $response = getJson(route('production.remnants.index', ['status' => 'invalid_status']));

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('rejects invalid warehouse_id with 422', function (): void {
    actingAs($this->admin);

    $response = getJson(route('production.remnants.index', ['warehouse_id' => 99999]));

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['warehouse_id']);
});
