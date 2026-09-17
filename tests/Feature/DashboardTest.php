<?php

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Enums\DashboardProfile;
use App\Enums\Permission;
use App\Enums\ProductionOrderStatus;
use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Enums\SystemRole;
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
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
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
    actingAsRole(SystemRole::Admin, ['email_verified_at' => now()]);

    $this->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard/Index')
            ->where('profile', DashboardProfile::Admin->value)
            ->where('roleLabel', SystemRole::Admin->label())
            ->has('stats.total_users')
            ->where('stats.pending_orders', 1)
            ->where('stats.active_orders', 1)
            ->where('stats.completed_today', 0)
            ->where('stats.unresolved_alerts', 1)
            ->has('recent_orders', 3)
            ->has('recent_alerts', 1)
            ->where('alert_breakdown.stock_bajo', 1));
});

test('production dashboard exposes operational stats', function () {
    actingAsRole(SystemRole::Production, ['email_verified_at' => now()]);

    $this->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard/Index')
            ->where('profile', DashboardProfile::Production->value)
            ->where('stats.pending_orders', 1)
            ->where('stats.active_orders', 1)
            ->where('stats.pending_review_orders', 1)
            ->where('stats.unresolved_alerts', 1)
            ->missing('stats.total_users')
            ->has('recent_orders', 3)
            ->has('recent_alerts', 1)
            ->where('alert_breakdown.stock_bajo', 1));
});

test('operator dashboard exposes plant stats', function () {
    actingAsRole(SystemRole::Operator, ['email_verified_at' => now()]);

    $this->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard/Index')
            ->where('profile', DashboardProfile::Plant->value)
            ->where('stats.pending_orders', 1)
            ->where('stats.active_orders', 1)
            ->where('stats.submitted_orders', 1)
            ->has('recent_orders', 3)
            ->missing('recent_alerts'));
});

test('comercial dashboard exposes sales stats', function () {
    $comercial = userWithRole(SystemRole::Commercial, ['email_verified_at' => now()]);

    $client = Client::create([
        'business_name' => 'Cliente Test',
        'nit' => '123456',
        'is_active' => true,
    ]);

    Quotation::create([
        'client_id' => $client->id,
        'quotation_number' => 1,
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
            ->where('profile', DashboardProfile::Commercial->value)
            ->where('stats.available_products', 1)
            ->where('stats.active_quotes', 1)
            ->where('stats.pending_orders', 2)
            ->where('stats.total_clients', 1)
            ->has('recent_quotes', 1)
            ->where('recent_quotes.0.total', 1190)
            ->has('recent_sales_orders', 2));
});

test('super-admin gets the admin dashboard', function () {
    actingAsRole(SystemRole::SuperAdmin, ['email_verified_at' => now()]);

    $this->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('profile', DashboardProfile::Admin->value)
            ->where('roleLabel', SystemRole::SuperAdmin->label()));
});

test('a custom role gets the view that matches its permissions and only its data', function () {
    $this->seed(RolePermissionSeeder::class);
    Role::create(['name' => 'calidad', 'guard_name' => 'web'])
        ->givePermissionTo([Permission::DashboardView->value, Permission::ProductionOrdersView->value]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('calidad');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('profile', DashboardProfile::Plant->value)
            ->where('roleLabel', 'calidad')
            ->where('stats.pending_orders', 1)
            ->missing('stats.total_users')
            ->missing('recent_alerts'));
});

test('a custom role without dashboard data gets an empty dashboard instead of an error', function () {
    $this->seed(RolePermissionSeeder::class);
    Role::create(['name' => 'visitante', 'guard_name' => 'web'])
        ->givePermissionTo(Permission::DashboardView->value);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('visitante');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('profile', DashboardProfile::None->value)
            ->where('stats', []));
});

test('a commercial-profile role with view_all sees everyone\'s quotations and orders', function () {
    $this->seed(RolePermissionSeeder::class);
    Role::create(['name' => 'jefe-ventas', 'guard_name' => 'web'])->givePermissionTo([
        Permission::DashboardView->value,
        Permission::QuotationsViewAll->value,
        Permission::SalesOrdersViewAll->value,
    ]);

    $manager = User::factory()->create(['email_verified_at' => now()]);
    $manager->assignRole('jefe-ventas');
    $seller = User::factory()->create();

    $client = Client::create(['business_name' => 'Cliente Ventas', 'nit' => '900111', 'is_active' => true]);

    Quotation::create([
        'client_id' => $client->id,
        'quotation_number' => 2,
        'status' => QuotationStatus::Draft,
        'subtotal' => 100,
        'iva_percentage' => 19,
        'iva_amount' => 19,
        'total' => 119,
        'created_by' => $seller->id,
    ]);

    SalesOrder::create([
        'client_id' => $client->id,
        'status' => SalesOrderStatus::Pending,
        'created_by' => $seller->id,
    ]);

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('profile', DashboardProfile::Commercial->value)
            ->where('stats.active_quotes', 1)
            ->where('stats.pending_orders', 1)
            ->has('recent_quotes', 1)
            ->has('recent_sales_orders', 1)
            ->missing('stats.total_clients'));
});

test('users without the dashboard.view permission receive a 403', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertForbidden();
});
