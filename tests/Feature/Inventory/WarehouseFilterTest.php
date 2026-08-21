<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('admin');

    $this->produccion = User::factory()->create(['email_verified_at' => now()]);
    $this->produccion->assignRole('produccion');

    $this->warehouseA = Warehouse::factory()->create([
        'name' => 'Bodega Principal',
        'city' => 'Bogotá',
        'address' => 'Calle 123',
    ]);

    $this->warehouseB = Warehouse::factory()->create([
        'name' => 'Bodega Norte',
        'city' => 'Medellín',
        'address' => 'Carrera 456',
    ]);

    $this->warehouseC = Warehouse::factory()->create([
        'name' => 'Bodega Sur',
        'city' => 'Cali',
        'address' => 'Avenida 789',
    ]);

    // Assign warehouseA to produccion user
    $this->warehouseA->users()->attach($this->produccion->id);
});

test('filters warehouses by name search', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('warehouses.index', ['search' => 'Principal']));

    $response->assertInertia(fn ($page) => $page
        ->has('warehouses.data', 1)
        ->where('warehouses.data.0.id', $this->warehouseA->id)
        ->where('filters.search', 'Principal')
    );
});

test('filters warehouses by city search', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('warehouses.index', ['search' => 'Medellín']));

    $response->assertInertia(fn ($page) => $page
        ->has('warehouses.data', 1)
        ->where('warehouses.data.0.id', $this->warehouseB->id)
    );
});

test('filters warehouses by address search', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('warehouses.index', ['search' => 'Avenida']));

    $response->assertInertia(fn ($page) => $page
        ->has('warehouses.data', 1)
        ->where('warehouses.data.0.id', $this->warehouseC->id)
    );
});

test('search is case insensitive', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('warehouses.index', ['search' => 'bodega']));

    $response->assertInertia(fn ($page) => $page
        ->has('warehouses.data', 3)
    );
});

test('whitespace in search is normalized', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('warehouses.index', ['search' => '  Bodega   Principal  ']));

    $response->assertInertia(fn ($page) => $page
        ->has('warehouses.data', 1)
        ->where('warehouses.data.0.id', $this->warehouseA->id)
    );
});

test('invalid filter keys are ignored', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('warehouses.index', ['search' => 'Bodega', 'invalid_key' => 'value']));

    $response->assertInertia(fn ($page) => $page
        ->has('warehouses.data')
        ->where('filters.search', 'Bodega')
        ->missing('filters.invalid_key')
    );
});

test('non admin user only sees assigned warehouses', function (): void {
    $response = $this->actingAs($this->produccion)
        ->get(route('warehouses.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('warehouses.data', 1)
        ->where('warehouses.data.0.id', $this->warehouseA->id)
    );
});

test('unauthorized users cannot access warehouses index', function (): void {
    $unauthorizedUser = User::factory()->create();

    $this->actingAs($unauthorizedUser)
        ->get(route('warehouses.index'))
        ->assertForbidden();
});
