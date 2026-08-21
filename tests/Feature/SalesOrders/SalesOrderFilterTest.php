<?php

declare(strict_types=1);

use App\Enums\SalesOrderPriority;
use App\Enums\SalesOrderStatus;
use App\Models\Client;
use App\Models\SalesOrder;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('admin');

    $this->comercial = User::factory()->create(['email_verified_at' => now()]);
    $this->comercial->assignRole('comercial');

    $this->clientA = Client::factory()->create(['business_name' => 'Alpha Corporation']);
    $this->clientB = Client::factory()->create(['business_name' => 'Beta Logistics']);

    $this->orderA = SalesOrder::factory()->create([
        'client_id' => $this->clientA->id,
        'status' => SalesOrderStatus::Pending->value,
        'priority' => SalesOrderPriority::Low->value,
        'required_date' => '2026-01-15',
        'created_by' => $this->comercial->id,
    ]);

    $this->orderB = SalesOrder::factory()->create([
        'client_id' => $this->clientB->id,
        'status' => SalesOrderStatus::Delivered->value,
        'priority' => SalesOrderPriority::High->value,
        'required_date' => '2026-02-25',
        'created_by' => $this->admin->id,
    ]);
});

it('renders sales orders index for admin', function () {
    $this->actingAs($this->admin)
        ->get(route('sales-orders.index'))
        ->assertInertia(fn ($page) => $page
            ->component('SalesOrders/Index')
            ->has('orders.data', 2)
            ->where('can.manage', true)
        );
});

it('shows only own orders to comercial users', function () {
    $this->actingAs($this->comercial)
        ->get(route('sales-orders.index'))
        ->assertInertia(fn ($page) => $page
            ->component('SalesOrders/Index')
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $this->orderA->id)
            ->where('can.manage', false)
        );
});

it('filters by status', function () {
    $this->actingAs($this->admin)
        ->get(route('sales-orders.index', ['status' => SalesOrderStatus::Pending->value]))
        ->assertInertia(fn ($page) => $page
            ->component('SalesOrders/Index')
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $this->orderA->id)
        );
});

it('filters by priority', function () {
    $this->actingAs($this->admin)
        ->get(route('sales-orders.index', ['priority' => SalesOrderPriority::High->value]))
        ->assertInertia(fn ($page) => $page
            ->component('SalesOrders/Index')
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $this->orderB->id)
        );
});

it('filters effectively by search term', function () {
    $this->actingAs($this->admin)
        ->get(route('sales-orders.index', ['search' => 'Alpha']))
        ->assertInertia(fn ($page) => $page
            ->component('SalesOrders/Index')
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $this->orderA->id)
        );
});

it('filters by date range', function () {
    $this->actingAs($this->admin)
        ->get(route('sales-orders.index', [
            'date_from' => '2026-02-01',
            'date_to' => '2026-02-28',
        ]))
        ->assertInertia(fn ($page) => $page
            ->component('SalesOrders/Index')
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $this->orderB->id)
        );
});

it('returns validated filters in inertia props', function () {
    $this->actingAs($this->admin)
        ->get(route('sales-orders.index', ['search' => 'test']))
        ->assertInertia(fn ($page) => $page
            ->where('filters.search', 'test')
        );
});

it('combines search and status filters', function () {
    $this->actingAs($this->admin)
        ->get(route('sales-orders.index', [
            'search' => 'Alpha',
            'status' => SalesOrderStatus::Pending->value,
        ]))
        ->assertInertia(fn ($page) => $page
            ->component('SalesOrders/Index')
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $this->orderA->id)
        );

    $this->actingAs($this->admin)
        ->get(route('sales-orders.index', [
            'search' => 'Alpha',
            'status' => SalesOrderStatus::Delivered->value,
        ]))
        ->assertInertia(fn ($page) => $page
            ->component('SalesOrders/Index')
            ->has('orders.data', 0)
        );
});

it('normalizes whitespace in search term', function () {
    $this->actingAs($this->admin)
        ->get(route('sales-orders.index', ['search' => '  Alpha   Corporation  ']))
        ->assertInertia(fn ($page) => $page
            ->component('SalesOrders/Index')
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $this->orderA->id)
        );
});

it('ignores invalid filter keys', function () {
    $this->actingAs($this->admin)
        ->get(route('sales-orders.index', ['invalid_key' => 'whatever']))
        ->assertInertia(fn ($page) => $page
            ->component('SalesOrders/Index')
            ->has('orders.data', 2)
            ->missing('filters.invalid_key')
        );
});

it('preserves query string in pagination links', function () {
    $this->actingAs($this->admin)
        ->get(route('sales-orders.index', ['search' => 'Alpha']))
        ->assertInertia(fn ($page) => $page
            ->component('SalesOrders/Index')
            ->has('orders.data', 1)
            ->has('orders.links')
        );
});

it('rejects an invalid date range', function () {
    $this->actingAs($this->admin)
        ->getJson(route('sales-orders.index', [
            'date_from' => '2026-03-01',
            'date_to' => '2026-01-01',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date_to']);
});

it('rejects an invalid status value', function () {
    $this->actingAs($this->admin)
        ->getJson(route('sales-orders.index', ['status' => 'nonexistent']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('rejects an invalid priority value', function () {
    $this->actingAs($this->admin)
        ->getJson(route('sales-orders.index', ['priority' => 'nonexistent']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['priority']);
});
