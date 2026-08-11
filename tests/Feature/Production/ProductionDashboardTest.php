<?php

declare(strict_types=1);

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Enums\ProductionOrderStatus;
use App\Models\Alert;
use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
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

    $this->productionUser = User::factory()->create(['email_verified_at' => now()]);
    $this->productionUser->assignRole('produccion');

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
        'created_by' => $this->productionUser->id,
    ]);

    ProductionOrder::create([
        'order_number' => 'OP-2026-0001',
        'product_id' => $this->product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 100,
        'status' => ProductionOrderStatus::Pending,
        'planned_date' => now()->toDateString(),
        'created_by' => $this->productionUser->id,
    ]);

    ProductionOrder::create([
        'order_number' => 'OP-2026-0002',
        'product_id' => $this->product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 50,
        'status' => ProductionOrderStatus::InProgress,
        'planned_date' => now()->toDateString(),
        'created_by' => $this->productionUser->id,
    ]);

    ProductionOrder::create([
        'order_number' => 'OP-2026-0003',
        'product_id' => $this->product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 200,
        'status' => ProductionOrderStatus::PendingReview,
        'planned_date' => now()->addDay()->toDateString(),
        'created_by' => $this->productionUser->id,
    ]);

    Alert::factory()->create([
        'type' => AlertType::StockBajo,
        'raw_material_id' => $rawMaterial->id,
        'severity' => AlertSeverity::Alta,
        'message' => 'Stock bajo de prueba',
    ]);
});

test('production dashboard exposes real operational stats', function (): void {
    $this->actingAs($this->productionUser)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard/Index')
            ->where('stats.pending_orders', 1)
            ->where('stats.active_orders', 1)
            ->where('stats.pending_review_orders', 1)
            ->where('stats.unresolved_alerts', 1)
            ->has('recent_orders', 3)
            ->has('recent_alerts', 1)
            ->where('alert_breakdown.stock_bajo', 1));
});

test('raw materials index includes active alert indicators', function (): void {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('raw-materials.index'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Inventory/RawMaterials/Index')
            ->has('rawMaterials.data', 1)
            ->where('rawMaterials.data.0.active_alerts_count', 1)
            ->where('rawMaterials.data.0.has_critical_alert', true));
});
