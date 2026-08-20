<?php

declare(strict_types=1);

use App\Enums\QuotationStatus;
use App\Models\Client;
use App\Models\Quotation;
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

    $this->produccion = User::factory()->create(['email_verified_at' => now()]);
    $this->produccion->assignRole('produccion');

    $this->clientA = Client::factory()->create([
        'business_name' => 'Acme Corp',
        'nit' => '123456789',
    ]);

    $this->clientB = Client::factory()->create([
        'business_name' => 'Beta Industries',
        'nit' => '987654321',
    ]);

    $this->quotationA = Quotation::factory()->create([
        'client_id' => $this->clientA->id,
        'client_business_name' => 'Acme Corp',
        'client_nit' => '123456789',
        'quotation_number' => 1001,
        'status' => QuotationStatus::Draft->value,
        'quotation_date' => now()->subDays(5)->toDateString(),
        'created_by' => $this->comercial->id,
        'total' => 1000,
    ]);

    $this->quotationB = Quotation::factory()->create([
        'client_id' => $this->clientB->id,
        'client_business_name' => 'Beta Industries',
        'client_nit' => '987654321',
        'quotation_number' => 1002,
        'status' => QuotationStatus::Sent->value,
        'quotation_date' => now()->subDays(10)->toDateString(),
        'created_by' => $this->comercial->id,
        'total' => 2000,
    ]);

    $this->quotationC = Quotation::factory()->create([
        'client_id' => $this->clientA->id,
        'client_business_name' => 'Acme Corp',
        'client_nit' => '123456789',
        'quotation_number' => 1003,
        'status' => QuotationStatus::Accepted->value,
        'quotation_date' => now()->subDays(1)->toDateString(),
        'created_by' => $this->admin->id,
        'total' => 3000,
    ]);
});

it('filters quotations by search term matching quotation number', function () {
    $this->actingAs($this->admin)
        ->get(route('quotations.index', ['search' => '1001']))
        ->assertInertia(fn ($page) => $page
            ->component('Quotations/Index')
            ->has('quotations.data', 1)
            ->where('quotations.data.0.id', $this->quotationA->id)
        );
});

it('filters quotations by search term matching client business name', function () {
    $this->actingAs($this->admin)
        ->get(route('quotations.index', ['search' => 'acme']))
        ->assertInertia(fn ($page) => $page
            ->component('Quotations/Index')
            ->has('quotations.data', 2)
        );
});

it('filters quotations by search term matching client nit', function () {
    $this->actingAs($this->admin)
        ->get(route('quotations.index', ['search' => '987654321']))
        ->assertInertia(fn ($page) => $page
            ->component('Quotations/Index')
            ->has('quotations.data', 1)
            ->where('quotations.data.0.id', $this->quotationB->id)
        );
});

it('filters quotations by status', function () {
    $this->actingAs($this->admin)
        ->get(route('quotations.index', ['status' => QuotationStatus::Draft->value]))
        ->assertInertia(fn ($page) => $page
            ->component('Quotations/Index')
            ->has('quotations.data', 1)
            ->where('quotations.data.0.id', $this->quotationA->id)
        );
});

it('filters quotations by creator id', function () {
    $this->actingAs($this->admin)
        ->get(route('quotations.index', ['created_by' => $this->admin->id]))
        ->assertInertia(fn ($page) => $page
            ->component('Quotations/Index')
            ->has('quotations.data', 1)
            ->where('quotations.data.0.id', $this->quotationC->id)
        );
});

it('filters quotations by date range', function () {
    $from = now()->subDays(7)->toDateString();
    $to = now()->toDateString();

    $this->actingAs($this->admin)
        ->get(route('quotations.index', ['date_from' => $from, 'date_to' => $to]))
        ->assertInertia(fn ($page) => $page
            ->component('Quotations/Index')
            ->has('quotations.data', 2)
        );
});

it('shows only own quotations to comercial users', function () {
    $this->actingAs($this->comercial)
        ->get(route('quotations.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Quotations/Index')
            ->has('quotations.data', 2)
        );
});

it('shows all quotations to admin users', function () {
    $this->actingAs($this->admin)
        ->get(route('quotations.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Quotations/Index')
            ->has('quotations.data', 3)
        );
});

it('forbids produccion users from quotations index', function () {
    $this->actingAs($this->produccion)
        ->get(route('quotations.index'))
        ->assertForbidden();
});

it('exposes creator filter only for admin', function () {
    $this->actingAs($this->comercial)
        ->get(route('quotations.index'))
        ->assertInertia(fn ($page) => $page
            ->where('can.filter_by_creator', false)
            ->has('creatorOptions', 0)
        );

    $this->actingAs($this->admin)
        ->get(route('quotations.index'))
        ->assertInertia(fn ($page) => $page
            ->where('can.filter_by_creator', true)
            ->has('creatorOptions')
        );
});

it('returns validated filters in inertia props', function () {
    $this->actingAs($this->admin)
        ->get(route('quotations.index', ['search' => 'acme', 'status' => 'draft']))
        ->assertInertia(fn ($page) => $page
            ->where('filters.search', 'acme')
            ->where('filters.status', 'draft')
        );
});

it('ignores invalid filter keys', function () {
    $this->actingAs($this->admin)
        ->get(route('quotations.index', ['invalid_key' => 'whatever']))
        ->assertInertia(fn ($page) => $page
            ->missing('filters.invalid_key')
        );
});

it('normalizes search with multiple spaces between words', function () {
    $this->actingAs($this->admin)
        ->get(route('quotations.index', ['search' => 'acme    corp']))
        ->assertInertia(fn ($page) => $page
            ->component('Quotations/Index')
            ->has('quotations.data', 2)
        );
});

it('ignores search with only whitespace', function () {
    $this->actingAs($this->admin)
        ->get(route('quotations.index', ['search' => '   ']))
        ->assertInertia(fn ($page) => $page
            ->component('Quotations/Index')
            ->has('quotations.data', 3)
        );
});

it('ignores created_by filter for non-admin users', function () {
    $this->actingAs($this->comercial)
        ->get(route('quotations.index', ['created_by' => $this->admin->id]))
        ->assertInertia(fn ($page) => $page
            ->component('Quotations/Index')
            ->has('quotations.data', 2)
            ->where('quotations.data.0.id', $this->quotationA->id)
            ->where('quotations.data.1.id', $this->quotationB->id)
        );
});
