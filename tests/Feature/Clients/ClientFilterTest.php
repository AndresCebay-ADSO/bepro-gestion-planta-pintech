<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('admin');

    $this->clientA = Client::factory()->create([
        'business_name' => 'Acme Corp',
        'nit' => '123456789',
    ]);

    $this->clientB = Client::factory()->create([
        'business_name' => 'Beta Industries',
        'nit' => '987654321',
    ]);

    $this->clientC = Client::factory()->create([
        'business_name' => 'Gamma Solutions',
        'nit' => '555555555',
    ]);
});

test('filters clients by business name search', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('clients.index', ['search' => 'Acme']));

    $response->assertInertia(fn ($page) => $page
        ->has('clients.data', 1)
        ->where('clients.data.0.id', $this->clientA->id)
        ->where('filters.search', 'Acme')
    );
});

test('filters clients by nit search', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('clients.index', ['search' => '987654321']));

    $response->assertInertia(fn ($page) => $page
        ->has('clients.data', 1)
        ->where('clients.data.0.id', $this->clientB->id)
        ->where('filters.search', '987654321')
    );
});

test('search is case insensitive', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('clients.index', ['search' => 'acme']));

    $response->assertInertia(fn ($page) => $page
        ->has('clients.data', 1)
        ->where('clients.data.0.id', $this->clientA->id)
    );
});

test('whitespace in search is normalized', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('clients.index', ['search' => '  Acme   Corp  ']));

    $response->assertInertia(fn ($page) => $page
        ->has('clients.data', 1)
        ->where('clients.data.0.id', $this->clientA->id)
    );
});

test('invalid filter keys are ignored', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('clients.index', ['search' => 'Acme', 'invalid_key' => 'value']));

    $response->assertInertia(fn ($page) => $page
        ->has('clients.data', 1)
        ->where('filters.search', 'Acme')
        ->missing('filters.invalid_key')
    );
});

test('pagination preserves query string', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('clients.index', ['search' => 'Corp']));

    $response->assertInertia(fn ($page) => $page
        ->has('clients.data', 1)
        ->has('clients.links')
    );
});

test('unauthorized users cannot access clients index', function (): void {
    $unauthorizedUser = User::factory()->create();

    $this->actingAs($unauthorizedUser)
        ->get(route('clients.index'))
        ->assertForbidden();
});
