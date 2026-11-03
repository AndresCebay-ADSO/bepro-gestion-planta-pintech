<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\InventoryMovement;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('sends the data of the movement detail, with the warehouse', function () {
    $movement = InventoryMovement::factory()->create(['cost_price' => '12.5']);

    actingAs(User::factory()->create()->assignRole('admin'))
        ->get(route('inventory-movements.show', $movement))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Inventory/Movements/Show')
            ->where('movement.warehouse.id', $movement->warehouse_id)
            ->has('movement.warehouse.name')
            ->has('movement.raw_material.code')
            ->has('movement.batch.lot_number')
            ->has('movement.cost_price')
            ->where('can.viewCosts', true));
});

it('hides the movement cost without costs.view', function () {
    $movement = InventoryMovement::factory()->create(['cost_price' => '12.5']);

    actingAs(User::factory()->create()->assignRole(SystemRole::Production->value))
        ->get(route('inventory-movements.show', $movement))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('movement.cost_price')
            ->where('can.viewCosts', false));
});
