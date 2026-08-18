<?php

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
    $user->assignRole('comercial');

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
    $user->assignRole('comercial');

    $this->actingAs($user)
        ->post(route('sales-orders.store'), [])
        ->assertSessionHasErrors(['client_id', 'priority', 'required_date', 'items']);
});

it('allows comercial to view their own orders', function () {
    $user = User::factory()->create();
    $user->assignRole('comercial');

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
    $userA->assignRole('comercial');

    $userB = User::factory()->create();
    $userB->assignRole('comercial');

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
    $comercial->assignRole('comercial');

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
    $user->assignRole('produccion');

    $order = SalesOrder::factory()->create(['status' => 'pending']);

    $this->actingAs($user)
        ->patch(route('sales-orders.update', $order), [
            'status' => 'in_progress',
        ])
        ->assertRedirect();

    $order->refresh();
    expect($order->status->value)->toBe('in_progress');
});

it('allows produccion to update priority without changing status', function () {
    $user = User::factory()->create();
    $user->assignRole('produccion');

    $order = SalesOrder::factory()->pending()->create(['priority' => 'low']);

    $this->actingAs($user)
        ->patch(route('sales-orders.update', $order), [
            'status' => 'pending',
            'priority' => 'high',
        ])
        ->assertRedirect();

    $order->refresh();
    expect($order->status->value)->toBe('pending');
    expect($order->priority->value)->toBe('high');
});

it('prevents invalid status transitions', function () {
    $user = User::factory()->create();
    $user->assignRole('produccion');

    $order = SalesOrder::factory()->pending()->create();

    $this->actingAs($user)
        ->patch(route('sales-orders.update', $order), [
            'status' => 'delivered',
        ])
        ->assertSessionHasErrors(['status']);
});

it('prevents produccion from accessing sales order create', function () {
    $user = User::factory()->create();
    $user->assignRole('produccion');

    $this->actingAs($user)
        ->get(route('sales-orders.create'))
        ->assertForbidden();
});

it('rejects orders for soft-deleted clients', function () {
    $user = User::factory()->create();
    $user->assignRole('comercial');

    $client = Client::factory()->create();
    $client->delete();
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
    $user->assignRole('comercial');

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
    $user->assignRole('comercial');

    $order = SalesOrder::factory()->create();

    $this->actingAs($user)
        ->patch(route('sales-orders.update', $order), [
            'status' => 'in_progress',
        ])
        ->assertForbidden();
});

it('prevents operador from accessing sales order routes', function () {
    $user = User::factory()->create();
    $user->assignRole('operador');

    $this->actingAs($user)
        ->get(route('sales-orders.index'))
        ->assertForbidden();
});

it('saves client snapshot data on order creation', function () {
    $user = User::factory()->create();
    $user->assignRole('comercial');

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
    $user->assignRole('comercial');

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
