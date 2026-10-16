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
 * CLI-01 · D-CLI2: la dirección de envío es de 255 caracteres en el cliente, en el pedido y al convertir una cotización.
 * Antes las reglas aceptaban 500 frente a una columna de 255, y en PostgreSQL daba 500.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(SystemRole::Admin->value);

    $unit = UnitOfMeasure::factory()->create();
    $category = ProductCategory::create(['name' => 'Cat dirección']);
    $this->product = Product::create(['name' => 'Producto dirección', 'category_id' => $category->id,
        'unit_of_measure_id' => $unit->id, 'is_active' => true]);
    $this->variant = ProductVariant::create(['product_id' => $this->product->id, 'code' => 'VAR-DIR',
        'name' => 'Galón', 'unit_of_measure_id' => $unit->id, 'presentation_value' => 1, 'current_price' => 1000,
        'is_active' => true]);
    $this->client = Client::factory()->create();
});

/**
 * Envía la dirección a cada endpoint que la recibe.
 */
function sendShippingAddress(object $t, string $endpoint, string $address): TestResponse
{
    $t->actingAs($t->admin);

    return match ($endpoint) {
        'crear cliente' => $t->post(route('clients.store'), [
            'business_name' => 'Cliente dirección', 'nit' => '900111222', 'shipping_address' => $address,
        ]),
        'editar cliente' => $t->put(route('clients.update', $t->client), [
            'business_name' => $t->client->business_name, 'shipping_address' => $address,
        ]),
        'crear pedido' => $t->post(route('sales-orders.store'), [
            'client_id' => $t->client->id, 'priority' => 'high', 'required_date' => plantToday(),
            'shipping_address' => $address,
            'items' => [['product_id' => $t->product->id, 'product_variant_id' => $t->variant->id, 'quantity' => 2]],
        ]),
        'editar pedido' => $t->patch(route('sales-orders.update', SalesOrder::factory()->pending()->create()), [
            'shipping_address' => $address,
        ]),
        'convertir cotización' => (function () use ($t, $address): TestResponse {
            $quotation = Quotation::factory()->create(['client_id' => $t->client->id, 'created_by' => $t->admin->id,
                'status' => QuotationStatus::Accepted]);
            $quotation->items()->create(['product_id' => $t->product->id, 'product_variant_id' => $t->variant->id,
                'type' => 'primer', 'description' => 'Esmalte', 'quantity' => 3, 'list_unit_price' => 1000,
                'price_adjustment_pct' => 0, 'unit_price' => 1000, 'subtotal' => 3000, 'sort_order' => 1]);

            return $t->post(route('quotations.convert-to-order', $quotation), [
                'priority' => 'high', 'required_date' => plantToday(), 'shipping_address' => $address,
            ]);
        })(),
    };
}

dataset('shipping address endpoints', ['crear cliente', 'editar cliente', 'crear pedido', 'editar pedido', 'convertir cotización']);

test('una dirección de 256 caracteres se rechaza con un error del campo', function (string $endpoint): void {
    sendShippingAddress($this, $endpoint, str_repeat('D', 256))->assertSessionHasErrors('shipping_address');
})->with('shipping address endpoints');

test('una dirección de 255 caracteres se acepta', function (string $endpoint): void {
    sendShippingAddress($this, $endpoint, str_repeat('D', 255))->assertSessionHasNoErrors();
})->with('shipping address endpoints');
