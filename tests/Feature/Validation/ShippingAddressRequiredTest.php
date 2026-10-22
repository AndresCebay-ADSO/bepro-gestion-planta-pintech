<?php

declare(strict_types=1);

use App\Enums\QuotationStatus;
use App\Enums\SystemRole;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

/*
 * Auditoría 3.6, PR 10 (D-CLI3): la dirección de entrega se confirma al crear el pedido y al convertir la cotización, no
 * se deja vacía. La cotización no la guarda (es la oferta; la dirección importa al despachar) y cambiarla en el pedido no
 * cambia la del cliente.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(SystemRole::Admin->value);
    $this->actingAs($this->admin);

    $unit = UnitOfMeasure::factory()->create();
    $category = ProductCategory::create(['name' => 'Cat dirección obligatoria']);
    $this->product = Product::create(['name' => 'Producto dirección obligatoria', 'category_id' => $category->id,
        'unit_of_measure_id' => $unit->id, 'is_active' => true]);
    $this->variant = ProductVariant::create(['product_id' => $this->product->id, 'code' => 'VAR-DIR-OBL',
        'name' => 'Galón', 'unit_of_measure_id' => $unit->id, 'presentation_value' => 1, 'current_price' => 1000,
        'is_active' => true]);
    $this->client = Client::factory()->create(['shipping_address' => 'Calle 1 # 2-3, Cali']);
});

function createOrderWith(object $t, array $override = []): TestResponse
{
    return $t->post(route('sales-orders.store'), array_replace([
        'client_id' => $t->client->id, 'priority' => 'high', 'required_date' => plantToday(),
        'shipping_address' => 'Calle 1 # 2-3, Cali',
        'items' => [['product_id' => $t->product->id, 'product_variant_id' => $t->variant->id, 'quantity' => 2]],
    ], $override));
}

function convertWith(object $t, array $override = []): TestResponse
{
    $quotation = Quotation::factory()->create(['client_id' => $t->client->id, 'created_by' => $t->admin->id,
        'status' => QuotationStatus::Accepted]);
    $quotation->items()->create(['product_id' => $t->product->id, 'product_variant_id' => $t->variant->id,
        'type' => 'primer', 'description' => 'Esmalte', 'quantity' => 3, 'list_unit_price' => 1000,
        'price_adjustment_pct' => 0, 'unit_price' => 1000, 'subtotal' => 3000, 'sort_order' => 1]);

    return $t->post(route('quotations.convert-to-order', $quotation), array_replace([
        'priority' => 'high', 'required_date' => plantToday(), 'shipping_address' => 'Calle 1 # 2-3, Cali',
    ], $override));
}

it('requires the shipping address when creating an order', function (mixed $address): void {
    createOrderWith($this, ['shipping_address' => $address])->assertSessionHasErrors('shipping_address');

    expect(SalesOrder::query()->count())->toBe(0);
})->with([null, '', '   ']);

it('requires the shipping address when converting a quotation', function (mixed $address): void {
    convertWith($this, ['shipping_address' => $address])->assertSessionHasErrors('shipping_address');

    expect(SalesOrder::query()->count())->toBe(0);
})->with([null, '', '   ']);

it('does not let an order be edited into having no address', function (): void {
    $order = SalesOrder::factory()->pending()->create(['shipping_address' => 'Calle 9 # 8-7']);

    $this->patch(route('sales-orders.update', $order), ['shipping_address' => ''])->assertSessionHasErrors('shipping_address');

    expect($order->fresh()->shipping_address)->toBe('Calle 9 # 8-7');
});

it('lets a partial edit leave the address alone', function (): void {
    $order = SalesOrder::factory()->pending()->create(['shipping_address' => 'Calle 9 # 8-7']);

    $this->patch(route('sales-orders.update', $order), ['notes' => 'Solo cambia la nota'])->assertSessionHasNoErrors();

    expect($order->fresh()->shipping_address)->toBe('Calle 9 # 8-7');
});

it('stores the address of the order and leaves the client address as it was', function (): void {
    createOrderWith($this, ['shipping_address' => 'Bodega 3, km 5 vía a Palmira'])->assertSessionHasNoErrors();
    convertWith($this, ['shipping_address' => 'Otra sede, Yumbo'])->assertSessionHasNoErrors();

    expect(SalesOrder::query()->orderBy('id')->pluck('shipping_address')->all())->toBe(['Bodega 3, km 5 vía a Palmira', 'Otra sede, Yumbo'])
        ->and($this->client->fresh()->shipping_address)->toBe('Calle 1 # 2-3, Cali');
});
