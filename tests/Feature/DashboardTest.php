<?php

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Enums\ProductionOrderStatus;
use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Models\Alert;
use App\Models\Client;
use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\Quotation;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use App\Models\SalesOrder;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'produccion']);
    Role::firstOrCreate(['name' => 'operador']);
    Role::firstOrCreate(['name' => 'comercial']);

    $systemUser = User::factory()->create();

    $this->unit = UnitOfMeasure::create([
        'code' => 'KG',
        'name' => 'Kilogramo',
        'symbol' => 'kg',
        'is_active' => true,
    ]);

    $this->category = ProductCategory::create([
        'code' => 'CAT',
        'name' => 'Categoría',
        'is_active' => true,
    ]);

    $this->product = Product::create([
        'code' => 'PROD-001',
        'name' => 'Producto Test',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'is_active' => true,
    ]);

    $this->warehouse = Warehouse::create([
        'name' => 'Bodega',
        'city' => 'Cali',
        'type' => 'factory',
        'is_active' => true,
    ]);

    $rawMaterialCategory = RawMaterialCategory::create([
        'code' => 'RMC',
        'name' => 'MP Cat',
        'is_active' => true,
    ]);

    $rawMaterial = RawMaterial::create([
        'code' => 'RM-DASH',
        'category_id' => $rawMaterialCategory->id,
        'unit_of_measure_id' => $this->unit->id,
        'minimum_stock' => 10,
        'alert_days_before_expiry' => 30,
        'is_active' => true,
    ]);

    $formula = Formula::create([
        'product_id' => $this->product->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $systemUser->id,
    ]);

    ProductionOrder::create([
        'order_number' => 'OP-2026-0001',
        'product_id' => $this->product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 100,
        'status' => ProductionOrderStatus::Pending,
        'planned_date' => now()->toDateString(),
        'created_by' => $systemUser->id,
    ]);

    ProductionOrder::create([
        'order_number' => 'OP-2026-0002',
        'product_id' => $this->product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 50,
        'status' => ProductionOrderStatus::InProgress,
        'planned_date' => now()->toDateString(),
        'created_by' => $systemUser->id,
    ]);

    ProductionOrder::create([
        'order_number' => 'OP-2026-0003',
        'product_id' => $this->product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 200,
        'status' => ProductionOrderStatus::PendingReview,
        'planned_date' => now()->addDay()->toDateString(),
        'created_by' => $systemUser->id,
    ]);

    Alert::factory()->create([
        'type' => AlertType::StockBajo,
        'raw_material_id' => $rawMaterial->id,
        'severity' => AlertSeverity::Alta,
        'message' => 'Stock bajo de prueba',
    ]);
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('admin dashboard exposes global stats', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard/Index')
            ->where('role', 'admin')
            ->where('stats.pending_orders', 1)
            ->where('stats.active_orders', 1)
            ->where('stats.completed_today', 0)
            ->where('stats.unresolved_alerts', 1)
            ->has('recent_orders', 3)
            ->has('recent_alerts', 1)
            ->where('alert_breakdown.stock_bajo', 1));
});

test('production dashboard exposes operational stats', function () {
    $productionUser = User::factory()->create(['email_verified_at' => now()]);
    $productionUser->assignRole('produccion');

    $this->actingAs($productionUser)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard/Index')
            ->where('role', 'produccion')
            ->where('stats.pending_orders', 1)
            ->where('stats.active_orders', 1)
            ->where('stats.pending_review_orders', 1)
            ->where('stats.unresolved_alerts', 1)
            ->has('recent_orders', 3)
            ->has('recent_alerts', 1)
            ->where('alert_breakdown.stock_bajo', 1));
});

test('operator dashboard exposes plant stats', function () {
    $operator = User::factory()->create(['email_verified_at' => now()]);
    $operator->assignRole('operador');

    $this->actingAs($operator)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard/Index')
            ->where('role', 'operador')
            ->where('stats.pending_orders', 1)
            ->where('stats.active_orders', 1)
            ->where('stats.submitted_orders', 1)
            ->has('recent_orders', 3));
});

test('comercial dashboard exposes sales stats', function () {
    $comercial = User::factory()->create(['email_verified_at' => now()]);
    $comercial->assignRole('comercial');

    $client = Client::create([
        'business_name' => 'Cliente Test',
        'nit' => '123456',
        'is_active' => true,
    ]);

    Quotation::create([
        'client_id' => $client->id,
        'quotation_number' => 'COT-001',
        'status' => QuotationStatus::Draft,
        'subtotal' => 1000,
        'iva_percentage' => 19,
        'iva_amount' => 190,
        'total' => 1190,
        'created_by' => $comercial->id,
    ]);

    SalesOrder::create([
        'client_id' => $client->id,
        'status' => SalesOrderStatus::Pending,
        'created_by' => $comercial->id,
    ]);

    SalesOrder::create([
        'client_id' => $client->id,
        'status' => SalesOrderStatus::InProgress,
        'created_by' => $comercial->id,
    ]);

    $this->actingAs($comercial)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard/Index')
            ->where('role', 'comercial')
            ->where('stats.available_products', 1)
            ->where('stats.active_quotes', 1)
            ->where('stats.pending_orders', 2)
            ->where('stats.total_clients', 1)
            ->has('recent_quotes', 1)
            ->where('recent_quotes.0.total', 1190)
            ->has('recent_sales_orders', 2));
});

test('users without a recognized role receive a 403', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertForbidden();
});
