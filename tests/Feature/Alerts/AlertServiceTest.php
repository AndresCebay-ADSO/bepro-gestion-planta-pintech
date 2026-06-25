<?php

declare(strict_types=1);

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Jobs\RecalculateRawMaterialReferencePrice;
use App\Models\Alert;
use App\Models\InventoryBatch;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AlertService;
use App\Services\InventoryService;
use App\Services\ProductionCostRecalculationService;
use App\Services\RawMaterialReferencePriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'produccion']);
    Role::firstOrCreate(['name' => 'comercial']);

    $this->unit = UnitOfMeasure::create([
        'code' => 'KG',
        'name' => 'Kilogramo',
        'symbol' => 'kg',
        'is_active' => true,
    ]);

    $this->category = RawMaterialCategory::create([
        'code' => 'CAT-ALERT',
        'name' => 'Categoría alertas',
        'is_active' => true,
    ]);

    $this->warehouse = Warehouse::create([
        'name' => 'Bodega Alertas',
        'city' => 'Cali',
        'type' => 'factory',
        'is_active' => true,
    ]);

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('admin');

    $this->productionUser = User::factory()->create(['email_verified_at' => now()]);
    $this->productionUser->assignRole('produccion');

    $this->alertService = app(AlertService::class);
});

function createRawMaterialForAlerts(array $overrides = []): RawMaterial
{
    return RawMaterial::create([
        'code' => 'RM-ALERT-'.fake()->unique()->numerify('###'),
        'category_id' => test()->category->id,
        'unit_of_measure_id' => test()->unit->id,
        'current_price' => 100,
        'previous_price' => null,
        'minimum_stock' => 10,
        'alert_days_before_expiry' => 30,
        'price_variation_threshold' => null,
        'tracks_inventory' => true,
        'is_active' => true,
        ...$overrides,
    ]);
}

test('it creates a low stock alert when available stock is at or below minimum', function (): void {
    $rawMaterial = createRawMaterialForAlerts(['minimum_stock' => 10]);

    InventoryBatch::create([
        'raw_material_id' => $rawMaterial->id,
        'warehouse_id' => $this->warehouse->id,
        'initial_quantity' => 8,
        'remaining_quantity' => 8,
        'unit_price' => 10,
        'entry_date' => now()->toDateString(),
    ]);

    $this->alertService->evaluateLowStock((int) $rawMaterial->id);

    $alert = Alert::query()->first();

    expect($alert)->not->toBeNull()
        ->and($alert->type)->toBe(AlertType::StockBajo)
        ->and($alert->severity)->toBe(AlertSeverity::Media)
        ->and($alert->message)->toContain($rawMaterial->code);
});

test('it auto resolves low stock alert when stock recovers', function (): void {
    $rawMaterial = createRawMaterialForAlerts(['minimum_stock' => 10]);

    $batch = InventoryBatch::create([
        'raw_material_id' => $rawMaterial->id,
        'warehouse_id' => $this->warehouse->id,
        'initial_quantity' => 8,
        'remaining_quantity' => 8,
        'unit_price' => 10,
        'entry_date' => now()->toDateString(),
    ]);

    $this->alertService->evaluateLowStock((int) $rawMaterial->id);
    expect(Alert::query()->where('is_resolved', false)->count())->toBe(1);

    $batch->update(['remaining_quantity' => 20]);
    $this->alertService->evaluateLowStock((int) $rawMaterial->id);

    expect(Alert::query()->where('is_resolved', false)->count())->toBe(0)
        ->and(Alert::query()->where('is_resolved', true)->count())->toBe(1);
});

test('it creates expiry alerts for batches within alert window', function (): void {
    Carbon::setTestNow('2026-06-11');

    $rawMaterial = createRawMaterialForAlerts(['alert_days_before_expiry' => 30]);

    $batch = InventoryBatch::create([
        'raw_material_id' => $rawMaterial->id,
        'warehouse_id' => $this->warehouse->id,
        'initial_quantity' => 5,
        'remaining_quantity' => 5,
        'unit_price' => 10,
        'entry_date' => now()->toDateString(),
        'expiry_date' => now()->addDays(10)->toDateString(),
        'lot_number' => 'LOT-EXP-01',
    ]);

    $this->alertService->evaluateBatchExpiry((int) $batch->id);

    $alert = Alert::query()->first();

    expect($alert)->not->toBeNull()
        ->and($alert->type)->toBe(AlertType::VencimientoProximo)
        ->and($alert->batch_id)->toBe($batch->id)
        ->and($alert->message)->toContain('vence el');

    Carbon::setTestNow();
});

test('it does not create price variation alert when threshold is not configured', function (): void {
    $rawMaterial = createRawMaterialForAlerts([
        'price_variation_threshold' => null,
        'current_price' => 120,
        'previous_price' => 100,
    ]);

    $this->alertService->evaluatePriceVariation($rawMaterial, '100', '120');

    expect(Alert::count())->toBe(0);
});

test('it creates price variation alert only for materials with configured threshold', function (): void {
    $rawMaterial = createRawMaterialForAlerts([
        'price_variation_threshold' => 5,
        'current_price' => 120,
        'previous_price' => 100,
    ]);

    $this->alertService->evaluatePriceVariation($rawMaterial, '100', '120');

    $alert = Alert::query()->first();

    expect($alert)->not->toBeNull()
        ->and($alert->type)->toBe(AlertType::VariacionPrecio)
        ->and($alert->message)->toContain('+')
        ->and($alert->message)->toContain($rawMaterial->code);
});

test('it creates price variation alert via async job when reference price changes', function (): void {
    $rawMaterial = createRawMaterialForAlerts([
        'price_variation_threshold' => 5,
        'current_price' => 100,
        'previous_price' => null,
        'tracks_inventory' => true,
    ]);

    InventoryBatch::create([
        'raw_material_id' => $rawMaterial->id,
        'warehouse_id' => $this->warehouse->id,
        'initial_quantity' => 10,
        'remaining_quantity' => 10,
        'unit_price' => 100,
        'entry_date' => now()->toDateString(),
    ]);

    InventoryBatch::create([
        'raw_material_id' => $rawMaterial->id,
        'warehouse_id' => $this->warehouse->id,
        'initial_quantity' => 10,
        'remaining_quantity' => 10,
        'unit_price' => 150,
        'entry_date' => now()->toDateString(),
    ]);

    $job = new RecalculateRawMaterialReferencePrice((int) $rawMaterial->id);
    $job->handle(
        app(RawMaterialReferencePriceService::class),
        app(ProductionCostRecalculationService::class),
        app(AlertService::class),
    );

    expect(Alert::query()
        ->where('type', AlertType::VariacionPrecio)
        ->where('is_resolved', false)
        ->exists())->toBeTrue();
});

test('it generates low stock alert after inventory exit movement', function (): void {
    $rawMaterial = createRawMaterialForAlerts(['minimum_stock' => 10]);

    $batch = InventoryBatch::create([
        'raw_material_id' => $rawMaterial->id,
        'warehouse_id' => $this->warehouse->id,
        'initial_quantity' => 15,
        'remaining_quantity' => 15,
        'unit_price' => 10,
        'entry_date' => now()->toDateString(),
    ]);

    app(InventoryService::class)->storeMovement([
        'raw_material_id' => $rawMaterial->id,
        'warehouse_id' => $this->warehouse->id,
        'batch_id' => $batch->id,
        'type' => 'exit',
        'quantity' => 6,
        'movement_date' => now()->toDateString(),
    ], (int) $this->admin->id);

    expect(Alert::query()
        ->where('type', AlertType::StockBajo)
        ->where('is_resolved', false)
        ->exists())->toBeTrue();
});

test('admin can view alerts index and resolve an alert', function (): void {
    $rawMaterial = createRawMaterialForAlerts();

    $alert = Alert::factory()->create([
        'type' => AlertType::StockBajo,
        'raw_material_id' => $rawMaterial->id,
        'severity' => AlertSeverity::Media,
        'message' => 'Alerta de prueba',
    ]);

    $this->actingAs($this->admin)
        ->get(route('alerts.index'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Alerts/Index')
            ->has('alerts.data', 1)
            ->where('stats.unresolved_count', 1));

    $this->actingAs($this->productionUser)
        ->patch(route('alerts.resolve', $alert))
        ->assertRedirect();

    $alert->refresh();

    expect($alert->is_resolved)->toBeTrue()
        ->and($alert->resolved_by)->toBe($this->productionUser->id);
});

test('comercial user cannot access alerts module', function (): void {
    $commercial = User::factory()->create(['email_verified_at' => now()]);
    $commercial->assignRole('comercial');

    $this->actingAs($commercial)
        ->get(route('alerts.index'))
        ->assertForbidden();
});

test('expiry scan command processes batches', function (): void {
    Carbon::setTestNow('2026-06-11');

    $rawMaterial = createRawMaterialForAlerts(['alert_days_before_expiry' => 30]);

    InventoryBatch::create([
        'raw_material_id' => $rawMaterial->id,
        'warehouse_id' => $this->warehouse->id,
        'initial_quantity' => 5,
        'remaining_quantity' => 5,
        'unit_price' => 10,
        'entry_date' => now()->toDateString(),
        'expiry_date' => now()->addDays(5)->toDateString(),
    ]);

    $this->artisan('alerts:check-expiry')->assertSuccessful();

    expect(Alert::query()->where('type', AlertType::VencimientoProximo)->count())->toBe(1);

    Carbon::setTestNow();
});
