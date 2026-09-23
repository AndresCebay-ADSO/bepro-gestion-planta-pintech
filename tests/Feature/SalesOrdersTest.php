<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function createTestProduct(): array
{
    $category = ProductCategory::create(['name' => 'Test Category']);
    $uom = UnitOfMeasure::create(['code' => 'U', 'name' => 'Unidad', 'symbol' => 'u']);

    $product = Product::create([
        'code' => 'TEST-001',
        'name' => 'Test Product',
        'category_id' => $category->id,
        'unit_of_measure_id' => $uom->id,
        'is_active' => true,
    ]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'code' => 'VAR-001',
        'name' => 'Test Variant',
        'unit_of_measure_id' => $uom->id,
        'is_active' => true,
    ]);

    return [$product, $variant];
}

it('allows comercial to create a sales order', function () {
    $user = User::factory()->create();
    $user->assignRole(SystemRole::Commercial->value);

    $client = Client::factory()->create();
    [$product, $variant] = createTestProduct();

    $this->actingAs($user)
        ->post(route('sales-orders.store'), [
            'client_id' => $client->id,
            'priority' => 'high',
            'required_date' => now()->addDays(5)->format('Y-m-d'),
            'estimated_delivery_date' => now()->addDays(10)->format('Y-m-d'),
            'notes' => 'Pedido urgente',
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => 10,
                ],
            ],
        ])
        ->assertRedirect();

    $order = SalesOrder::latest()->first();
    expect($order)->not->toBeNull();
    expect($order->client_id)->toBe($client->id);
    expect($order->created_by)->toBe($user->id);
    expect($order->items)->toHaveCount(1);
});

it('validates required fields when creating a sales order', function () {
    $user = User::factory()->create();
    $user->assignRole(SystemRole::Commercial->value);

    $this->actingAs($user)
        ->post(route('sales-orders.store'), [])
        ->assertSessionHasErrors(['client_id', 'priority', 'required_date', 'items']);
});

it('allows comercial to view their own orders', function () {
    $user = User::factory()->create();
    $user->assignRole(SystemRole::Commercial->value);

    $order = SalesOrder::factory()->create(['created_by' => $user->id]);

    $this->actingAs($user)
        ->get(route('sales-orders.index'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('sales-orders.show', $order))
        ->assertOk();
});

it('prevents comercial from viewing other users orders', function () {
    $userA = User::factory()->create();
    $userA->assignRole(SystemRole::Commercial->value);

    $userB = User::factory()->create();
    $userB->assignRole(SystemRole::Commercial->value);

    $order = SalesOrder::factory()->create(['created_by' => $userB->id]);

    $this->actingAs($userA)
        ->get(route('sales-orders.show', $order))
        ->assertForbidden();
});

it('allows admin to access sales orders', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $order = SalesOrder::factory()->create();

    $this->actingAs($admin)
        ->get(route('sales-orders.index'))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('sales-orders.show', $order))
        ->assertOk();
});

it('exposes viewQuotation permission on order linked to quotation', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $comercial = User::factory()->create();
    $comercial->assignRole(SystemRole::Commercial->value);

    $quotation = Quotation::factory()->create(['created_by' => $comercial->id]);
    $order = SalesOrder::factory()->create(['quotation_id' => $quotation->id, 'created_by' => $comercial->id]);

    $this->actingAs($admin)
        ->get(route('sales-orders.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('can.viewQuotation', true)
        );

    $this->actingAs($comercial)
        ->get(route('sales-orders.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('can.viewQuotation', true)
        );
});

it('allows produccion to update sales order status', function () {
    $user = User::factory()->create();
    $user->assignRole(SystemRole::Production->value);

    $order = SalesOrder::factory()->create(['status' => 'pending']);

    $this->actingAs($user)
        ->patch(route('sales-orders.update-status', $order), [
            'status' => 'in_progress',
        ])
        ->assertRedirect();

    $order->refresh();
    expect($order->status->value)->toBe('in_progress');
});

it('prevents produccion from editing order data', function () {
    $user = User::factory()->create();
    $user->assignRole(SystemRole::Production->value);

    $order = SalesOrder::factory()->pending()->create(['priority' => 'low']);

    $this->actingAs($user)
        ->patch(route('sales-orders.update', $order), ['priority' => 'high'])
        ->assertForbidden();

    expect($order->fresh()->priority->value)->toBe('low');
});

it('allows admin to edit data of a pending order without touching its status', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $order = SalesOrder::factory()->pending()->create(['priority' => 'low']);

    $this->actingAs($admin)
        ->patch(route('sales-orders.update', $order), [
            'priority' => 'high',
            'status' => 'in_progress',
        ])
        ->assertRedirect();

    $order->refresh();
    expect($order->priority->value)->toBe('high')
        ->and($order->status->value)->toBe('pending');
});

it('prevents editing order data once the order is in progress', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $order = SalesOrder::factory()->create(['status' => 'in_progress', 'priority' => 'low']);

    $this->actingAs($admin)
        ->patch(route('sales-orders.update', $order), ['priority' => 'high'])
        ->assertForbidden();

    expect($order->fresh()->priority->value)->toBe('low');
});

it('prevents invalid status transitions', function () {
    $user = User::factory()->create();
    $user->assignRole(SystemRole::Production->value);

    $order = SalesOrder::factory()->pending()->create();

    $this->actingAs($user)
        ->patch(route('sales-orders.update-status', $order), [
            'status' => 'delivered',
        ])
        ->assertSessionHasErrors(['status']);
});

it('prevents produccion from accessing sales order create', function () {
    $user = User::factory()->create();
    $user->assignRole(SystemRole::Production->value);

    $this->actingAs($user)
        ->get(route('sales-orders.create'))
        ->assertForbidden();
});

it('rejects orders for inactive clients', function () {
    $user = User::factory()->create();
    $user->assignRole(SystemRole::Commercial->value);

    $client = Client::factory()->create(['is_active' => false]);
    [$product, $variant] = createTestProduct();

    $this->actingAs($user)
        ->post(route('sales-orders.store'), [
            'client_id' => $client->id,
            'priority' => 'medium',
            'required_date' => now()->addDays(5)->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => 5,
                ],
            ],
        ])
        ->assertSessionHasErrors(['client_id']);
});

it('rejects orders with inactive products', function () {
    $user = User::factory()->create();
    $user->assignRole(SystemRole::Commercial->value);

    $client = Client::factory()->create();
    [$product, $variant] = createTestProduct();
    $product->update(['is_active' => false]);

    $this->actingAs($user)
        ->post(route('sales-orders.store'), [
            'client_id' => $client->id,
            'priority' => 'medium',
            'required_date' => now()->addDays(5)->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => 5,
                ],
            ],
        ])
        ->assertSessionHasErrors(['items.0.product_id']);
});

it('prevents comercial from updating sales order status', function () {
    $user = User::factory()->create();
    $user->assignRole(SystemRole::Commercial->value);

    $order = SalesOrder::factory()->create();

    $this->actingAs($user)
        ->patch(route('sales-orders.update-status', $order), [
            'status' => 'in_progress',
        ])
        ->assertForbidden();
});

it('prevents operador from accessing sales order routes', function () {
    $user = User::factory()->create();
    $user->assignRole(SystemRole::Operator->value);

    $this->actingAs($user)
        ->get(route('sales-orders.index'))
        ->assertForbidden();
});

it('saves client snapshot data on order creation', function () {
    $user = User::factory()->create();
    $user->assignRole(SystemRole::Commercial->value);

    $client = Client::factory()->create();
    [$product, $variant] = createTestProduct();

    $this->actingAs($user)
        ->post(route('sales-orders.store'), [
            'client_id' => $client->id,
            'priority' => 'low',
            'client_business_name' => $client->business_name,
            'client_nit' => $client->nit,
            'client_contact_name' => 'Pedido Contact',
            'client_phone' => '555-9999',
            'required_date' => now()->addDays(5)->format('Y-m-d'),
            'items' => [
                ['product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity' => 5],
            ],
        ])
        ->assertRedirect();

    $order = SalesOrder::latest()->first();
    expect($order->client_business_name)->toBe($client->business_name);
    expect($order->client_nit)->toBe($client->nit);
    expect($order->client_contact_name)->toBe('Pedido Contact');
    expect($order->client_phone)->toBe('555-9999');
});

it('filters orders by status', function () {
    $user = User::factory()->create();
    $user->assignRole(SystemRole::Commercial->value);

    SalesOrder::factory()->pending()->create(['created_by' => $user->id]);
    SalesOrder::factory()->delivered()->create(['created_by' => $user->id]);

    $this->actingAs($user)
        ->get(route('sales-orders.index', ['status' => 'pending']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.status', 'pending')
        );
});

it('does not expose product costs or creator contact data on the sales order screens', function () {
    $user = User::factory()->create();
    $user->assignRole(SystemRole::Commercial->value);

    [$product, $variant] = createTestProduct();
    $product->forceFill([
        'current_cost' => 50,
        'cif_percentage' => 20,
        'price_threshold' => 5,
        'sales_margin' => 30,
    ])->save();

    $order = SalesOrder::factory()->create(['created_by' => $user->id, 'status' => 'pending']);
    SalesOrderItem::create([
        'sales_order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'quantity' => 5,
    ]);

    $this->actingAs($user)
        ->get(route('sales-orders.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('order.items.0.product.name', 'Test Product')
            ->missing('order.items.0.product.current_cost')
            ->missing('order.items.0.product.cif_percentage')
            ->missing('order.items.0.product.price_threshold')
            ->missing('order.items.0.product.sales_margin')
            ->missing('order.items.0.product_variant.current_cost')
            ->where('order.creator.name', $user->name)
            ->missing('order.creator.email'));

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('sales-orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('orders.data.0.creator.name', $user->name)
            ->missing('orders.data.0.creator.email')
            ->missing('orders.data.0.client.nit'));
});
