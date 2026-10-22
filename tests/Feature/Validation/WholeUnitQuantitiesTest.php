<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\Client;
use App\Models\FinishedInventoryMovement;
use App\Models\FinishedProductBatch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\ProductVariant;
use App\Models\QuotationItem;
use App\Models\SalesOrderItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Auditoría 3.6, PR 4: las cantidades de cotización y de pedido, y el inventario de PT, se cuentan en unidades enteras
 * de una presentación (P4-1). El tamaño de la presentación sí lleva decimales (un cuarto es 0,25 gal).
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(SystemRole::Admin->value);

    $unit = UnitOfMeasure::factory()->create();
    $category = ProductCategory::create(['name' => 'Cat unidades']);
    $this->product = Product::create(['name' => 'Producto unidades', 'category_id' => $category->id,
        'unit_of_measure_id' => $unit->id, 'sales_margin' => 25, 'cif_percentage' => 15, 'price_threshold' => 3,
        'current_price' => 1000, 'is_active' => true]);
    $this->variant = ProductVariant::create(['product_id' => $this->product->id, 'code' => 'VAR-UNI',
        'name' => 'Cuarto', 'unit_of_measure_id' => $unit->id, 'presentation_value' => '0.25', 'current_price' => 1000,
        'is_active' => true]);
    $this->client = Client::factory()->create();
});

/**
 * @return array<string, mixed>
 */
function wholeUnitsQuotationPayload(object $t, array $item): array
{
    return [
        'client_id' => $t->client->id,
        'client_business_name' => 'Cliente unidades',
        'client_nit' => '900111222',
        'quotation_date' => plantToday(),
        'validity_days' => 30,
        'payment_method' => 'cash',
        'iva_percentage' => 19,
        'items' => [array_replace([
            'product_id' => $t->product->id,
            'product_variant_id' => $t->variant->id,
            'type' => 'primer',
            'description' => 'Esmalte',
            'quantity' => 3,
            'price_adjustment_pct' => 0,
        ], $item)],
    ];
}

test('la cantidad de una cotización es un número entero de unidades', function (): void {
    $this->actingAs($this->admin)
        ->post(route('quotations.store'), wholeUnitsQuotationPayload($this, ['quantity' => '1.5']))
        ->assertSessionHasErrors('items.0.quantity');

    $this->actingAs($this->admin)
        ->post(route('quotations.store'), wholeUnitsQuotationPayload($this, ['quantity' => '3']))
        ->assertSessionHasNoErrors();

    expect(QuotationItem::query()->sole()->quantity)->toBe(3);
});

test('con precio manual se guarda el precio digitado (pesos enteros) y el ajuste con 2 decimales', function (): void {
    $this->actingAs($this->admin)
        ->post(route('quotations.store'), wholeUnitsQuotationPayload($this, ['unit_price' => '1235']))
        ->assertSessionHasNoErrors();

    $item = QuotationItem::query()->sole();
    // Lista = 1.000 / (1 − 25 %) = 1.333,3333; ajuste = (1 − 1.333,3333 / 1.235) × 100 = −7,96.
    expect(bccomp((string) $item->unit_price, '1235', 4))->toBe(0)
        ->and((string) $item->price_adjustment_pct)->toBe('-7.96');
});

test('la cantidad de un pedido es un número entero de unidades', function (): void {
    $payload = fn (string $quantity): array => [
        'client_id' => $this->client->id, 'priority' => 'high', 'required_date' => plantToday(),
        'shipping_address' => 'Calle 1 # 2-3, Cali',
        'items' => [['product_id' => $this->product->id, 'product_variant_id' => $this->variant->id, 'quantity' => $quantity]],
    ];

    $this->actingAs($this->admin)->post(route('sales-orders.store'), $payload('1.5'))
        ->assertSessionHasErrors('items.0.quantity');

    $this->actingAs($this->admin)->post(route('sales-orders.store'), $payload('2'))
        ->assertSessionHasNoErrors();

    expect(SalesOrderItem::query()->sole()->quantity)->toBe(2);
});

test('los movimientos de PT se registran en unidades enteras', function (): void {
    $batch = FinishedProductBatch::create([
        'product_id' => $this->product->id,
        'production_order_id' => ProductionOrder::factory()->create(['product_id' => $this->product->id])->id,
        'product_variant_id' => $this->variant->id,
        'initial_quantity' => '20',
        'entry_date' => now(),
    ]);
    $warehouse = Warehouse::factory()->create();
    $payload = fn (string $quantity): array => [
        'finished_product_batch_id' => $batch->id, 'warehouse_id' => $warehouse->id, 'type' => 'entry',
        'reason' => 'adjustment', 'quantity' => $quantity, 'movement_date' => plantToday(),
    ];

    $this->actingAs($this->admin)->post(route('finished-inventory-movements.store'), $payload('1.5'))
        ->assertSessionHasErrors('quantity');

    $this->actingAs($this->admin)->post(route('finished-inventory-movements.store'), $payload('2'))
        ->assertSessionHasNoErrors();

    expect(FinishedInventoryMovement::query()->sole()->quantity)->toBe(2);
});
