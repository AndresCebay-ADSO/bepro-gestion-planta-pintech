<?php

use App\Models\Client;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('allows admin to list clients', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Client::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('clients.index'))
        ->assertOk();
});

it('allows admin to create a client', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('clients.store'), [
            'business_name' => 'Test Client',
            'nit' => '12345678901',
            'contact_name' => 'John Doe',
            'phone' => '555-1234',
        ])
        ->assertRedirect();

    expect(Client::where('business_name', 'Test Client')->exists())->toBeTrue();
});

it('allows comercial to create a client', function () {
    $user = User::factory()->create();
    $user->assignRole('comercial');

    $this->actingAs($user)
        ->post(route('clients.store'), [
            'business_name' => 'Comercial Client',
        ])
        ->assertRedirect();
});

it('prevents comercial from editing a client', function () {
    $user = User::factory()->create();
    $user->assignRole('comercial');

    $client = Client::factory()->create();

    $this->actingAs($user)
        ->put(route('clients.update', $client), [
            'business_name' => 'Hacked Name',
        ])
        ->assertForbidden();
});

it('allows admin to edit a client', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $client = Client::factory()->create();

    $this->actingAs($admin)
        ->put(route('clients.update', $client), [
            'business_name' => 'Updated Name',
        ])
        ->assertRedirect();

    $client->refresh();
    expect($client->business_name)->toBe('Updated Name');
});

it('allows admin to delete a client', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $client = Client::factory()->create();

    $this->actingAs($admin)
        ->delete(route('clients.destroy', $client))
        ->assertRedirect();

    expect(Client::find($client->id))->toBeNull();
});

it('validates required business_name on create', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('clients.store'), [])
        ->assertSessionHasErrors(['business_name']);
});
