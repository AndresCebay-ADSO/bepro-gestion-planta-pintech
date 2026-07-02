<?php

declare(strict_types=1);

use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function createMetadataDependencies(): array
{
    $category = ProductCategory::create(['name' => 'Paint Category']);
    $uom = UnitOfMeasure::create([
        'code' => 'l',
        'name' => 'Litro',
        'symbol' => 'L',
    ]);

    $user = User::create([
        'name' => 'Operator User',
        'email' => 'operator@example.com',
        'password' => Hash::make('password'),
    ]);

    $product = Product::create([
        'code' => 'PNT-123',
        'name' => 'Super Paint',
        'category_id' => $category->id,
        'unit_of_measure_id' => $uom->id,
        'current_cost' => 10,
        'cif_percentage' => 50,
        'current_price' => 15,
        'price_threshold' => 5,
    ]);

    $formula = Formula::create([
        'product_id' => $product->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    $warehouse = Warehouse::create([
        'name' => 'Factory A',
        'city' => 'Medellin',
        'type' => 'factory',
    ]);

    return [$product, $user, $formula, $warehouse];
}

test('permite guardar y recuperar metadata operacional en una orden de producción', function () {
    [$product, $user, $formula, $warehouse] = createMetadataDependencies();

    $agitationStart = now()->subMinutes(60);
    $agitationEnd = now()->subMinutes(30);

    $order = ProductionOrder::create([
        'order_number' => 'OP-METADATA-001',
        'product_id' => $product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 100,
        'status' => 'pending',
        'planned_date' => now(),
        'created_by' => $user->id,

        // Metadata operacional
        'agitation_start_time' => $agitationStart,
        'agitation_end_time' => $agitationEnd,
        'viscosity_ku' => 105.50,
        'grinding_hg' => 7.25,
        'responsible_name' => 'Juan Perez',
        'spillage_quantity' => 2.50,
    ]);

    $order->refresh();

    expect($order->agitation_start_time->format('Y-m-d H:i:s'))->toBe($agitationStart->format('Y-m-d H:i:s'));
    expect($order->agitation_end_time->format('Y-m-d H:i:s'))->toBe($agitationEnd->format('Y-m-d H:i:s'));
    expect((float) $order->viscosity_ku)->toBe(105.5);
    expect((float) $order->grinding_hg)->toBe(7.25);
    expect($order->responsible_name)->toBe('Juan Perez');
    expect((float) $order->spillage_quantity)->toBe(2.5);
});
