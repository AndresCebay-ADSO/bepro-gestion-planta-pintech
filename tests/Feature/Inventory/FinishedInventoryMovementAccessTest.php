<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'produccion']);
    Role::firstOrCreate(['name' => 'comercial']);
});

it('allows admin and produccion to access finished inventory movements index', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $produccion = User::factory()->create()->assignRole('produccion');

    actingAs($admin)
        ->get(route('finished-inventory-movements.index'))
        ->assertOk();

    actingAs($produccion)
        ->get(route('finished-inventory-movements.index'))
        ->assertOk();
});

it('forbids comercial from accessing finished inventory movements index', function () {
    $comercial = User::factory()->create()->assignRole('comercial');

    actingAs($comercial)
        ->get(route('finished-inventory-movements.index'))
        ->assertForbidden();
});

it('allows comercial to access finished inventory index', function () {
    $comercial = User::factory()->create()->assignRole('comercial');

    actingAs($comercial)
        ->get(route('finished-inventory.index'))
        ->assertOk();
});
