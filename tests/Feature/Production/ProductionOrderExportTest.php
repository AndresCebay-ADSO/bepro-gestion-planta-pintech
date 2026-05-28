<?php

declare(strict_types=1);

use App\Actions\Production\BuildProductionOrderExportDataAction;
use App\Actions\Production\BuildProductionOrderPdfMaterialsAction;
use App\Actions\Production\BuildProductionOrderShowDataAction;
use App\Exports\ProductionOrderExport;
use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::create(['name' => 'admin']);
    Role::create(['name' => 'produccion']);
    Role::create(['name' => 'comercial']);
});

function createExportTestDependencies(): array
{
    $category = ProductCategory::create(['name' => 'Pinturas Export']);
    $rmCategory = RawMaterialCategory::create([
        'code' => 'RM-EXP-CAT',
        'name' => 'MP Export Test',
        'is_active' => true,
    ]);
    $uom = UnitOfMeasure::create([
        'code' => 'gal-exp',
        'name' => 'Galón',
        'symbol' => 'gal',
    ]);

    $user = User::create([
        'name' => 'Export Test User',
        'email' => 'export-test@example.com',
        'password' => Hash::make('password'),
    ]);
    $user->assignRole('produccion');

    $product = Product::create([
        'code' => 'PNT-EXP-01',
        'name' => 'Pintura Export Test',
        'category_id' => $category->id,
        'unit_of_measure_id' => $uom->id,
        'current_cost' => 35,
        'profit_margin' => 25,
        'current_price' => 43.75,
        'price_threshold' => 5,
    ]);

    $formula = Formula::create([
        'product_id' => $product->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    $warehouse = Warehouse::create([
        'name' => 'Planta Export',
        'city' => 'Cali',
        'type' => 'factory',
    ]);

    $rawMaterial = RawMaterial::create([
        'code' => 'RM-EXP-01',
        'category_id' => $rmCategory->id,
        'unit_of_measure_id' => $uom->id,
        'current_price' => 10,
        'previous_price' => null,
        'minimum_stock' => 0,
        'is_active' => true,
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-EXP-0001',
        'product_id' => $product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 50,
        'status' => 'pending',
        'planned_date' => now(),
        'created_by' => $user->id,
        'responsible_name' => 'Operario Test',
        'spillage_quantity' => 0,
    ]);

    ProductionOrderDetail::create([
        'production_order_id' => $order->id,
        'raw_material_id' => $rawMaterial->id,
        'planned_quantity' => 100,
        'unit_cost' => 10,
        'total_cost' => 1000,
    ]);

    return [$order, $user];
}

test('exportPdf returns a PDF download for an authenticated user', function () {
    [$order, $user] = createExportTestDependencies();

    $response = $this->actingAs($user)
        ->get(route('production-orders.export-pdf', $order));

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/pdf');
    expect(strlen($response->content()))->toBeGreaterThan(0);
});

test('exportExcel returns an Excel download for an authenticated user', function () {
    [$order, $user] = createExportTestDependencies();

    $response = $this->actingAs($user)
        ->get(route('production-orders.export-excel', $order));

    $response->assertSuccessful();

    $contentType = $response->headers->get('content-type');
    expect($contentType)->toContain('spreadsheet');

    if ($response->baseResponse instanceof BinaryFileResponse) {
        expect($response->baseResponse->getFile()->getSize())->toBeGreaterThan(0);
    } else {
        expect(strlen($response->getContent()))->toBeGreaterThan(0);
    }
});

test('production order excel export skips missing logo drawing', function () {
    $export = new ProductionOrderExport(
        orderData: ['order_number' => 'OP-NO-LOGO'],
        logoPath: base_path('missing-logo-pintech.png')
    );

    expect($export->drawings())->toBe([]);
});

test('export routes require authentication', function () {
    [$order] = createExportTestDependencies();

    $this->get(route('production-orders.export-pdf', $order))
        ->assertRedirect('/login');

    $this->get(route('production-orders.export-excel', $order))
        ->assertRedirect('/login');
});

test('pdf payload uses raw material code when name is unavailable', function () {
    [$order, $user] = createExportTestDependencies();

    $this->actingAs($user);
    $materialsPayload = app(BuildProductionOrderPdfMaterialsAction::class)->execute($order);

    expect($materialsPayload)->toBeArray();
    expect($materialsPayload['rows'][0]['raw_material_code'])->toBe('RM-EXP-01');
    expect($materialsPayload['rows'][0]['raw_material_name'])->toBe('RM-EXP-01');
});

test('pdf materials payload provides decimal totals for export views', function () {
    [$order, $user] = createExportTestDependencies();
    $rawMaterial = RawMaterial::query()->where('code', 'RM-EXP-01')->firstOrFail();

    ProductionOrderDetail::create([
        'production_order_id' => $order->id,
        'raw_material_id' => $rawMaterial->id,
        'step_order' => 2,
        'planned_quantity' => '0.0375',
        'unit_cost' => '10',
        'total_cost' => '0.3750',
    ]);

    $this->actingAs($user);
    $materialsPayload = app(BuildProductionOrderPdfMaterialsAction::class)->execute($order->refresh());

    expect($materialsPayload['totals']['planned_quantity'])->toBe('100.0375');
    expect($materialsPayload['totals']['kg'])->toBe('100.0000');
    expect($materialsPayload['totals']['grams'])->toBe('37.5000');
    expect($materialsPayload['rows'][1]['display_grams'])->toBe('37.5000');
});

test('production order payload keeps calculated costs as decimal strings', function () {
    [$order, $user] = createExportTestDependencies();

    $this->actingAs($user);
    $payload = app(BuildProductionOrderExportDataAction::class)->execute($order);

    expect($payload['total_bulk_cost'])->toBe('1000.0000');
    expect($payload['details'][0]['unit_cost'])->toBe('10.0000');
    expect($payload['details'][0]['total_cost'])->toBe('1000.0000');
    expect($payload)->toHaveKey('pdf_materials');
});

test('show payload does not include export materials', function () {
    [$order, $user] = createExportTestDependencies();

    $this->actingAs($user);
    $payload = app(BuildProductionOrderShowDataAction::class)->execute($order);

    expect($payload)->not->toHaveKey('pdf_materials');
});
