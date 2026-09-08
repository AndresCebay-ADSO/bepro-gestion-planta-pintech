<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('admin can view assign users page and it only lists active users', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    $activeUser = User::factory()->create(['name' => 'Activo User', 'is_active' => true]);
    $inactiveUser = User::factory()->create(['name' => 'Inactivo User', 'is_active' => false]);
    $warehouse = Warehouse::factory()->create();

    $this->actingAs($admin)
        ->get(route('warehouses.assign-users.form', $warehouse))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Warehouses/AssignUsers')
            ->has('users', 2) // $admin + $activeUser (both active)
            ->where('users', fn ($users) => collect($users)->contains('id', $activeUser->id)
                && ! collect($users)->contains('id', $inactiveUser->id)
            )
        );
});

test('admin can assign active users to a warehouse', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    $user1 = User::factory()->create(['is_active' => true]);
    $user2 = User::factory()->create(['is_active' => true]);
    $warehouse = Warehouse::factory()->create();

    $this->actingAs($admin)
        ->post(route('warehouses.assign-users', $warehouse), [
            'users' => [
                ['user_id' => $user1->id, 'is_default' => true],
                ['user_id' => $user2->id, 'is_default' => false],
            ],
        ])
        ->assertRedirect(route('warehouses.show', $warehouse));

    expect($warehouse->fresh()->users)->toHaveCount(2);
});

test('non admin user cannot access assign users page or submit assignments', function () {
    $comercial = User::factory()->create();
    $comercial->assignRole('comercial');
    $warehouse = Warehouse::factory()->create();

    $this->actingAs($comercial)
        ->get(route('warehouses.assign-users.form', $warehouse))
        ->assertForbidden();

    $this->actingAs($comercial)
        ->post(route('warehouses.assign-users', $warehouse), [
            'users' => [
                ['user_id' => $comercial->id],
            ],
        ])
        ->assertForbidden();
});
