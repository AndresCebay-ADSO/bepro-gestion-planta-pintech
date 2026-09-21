<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\Warehouse;
use Inertia\Testing\AssertableInertia as Assert;

// La prop compartida `warehouseContext` es una closure: estos tests fijan que sigue llegando al renderizar una página.

test('a page shares the default warehouse as current and keeps it in session', function () {
    $admin = userWithRole(SystemRole::Admin);
    Warehouse::factory()->create(['name' => 'Bodega A']);
    $default = Warehouse::factory()->create(['name' => 'Bodega B']);
    $admin->warehouses()->attach($default->id, ['is_default' => true]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSessionHas('current_warehouse_id', $default->id)
        ->assertInertia(fn (Assert $page) => $page
            ->where('warehouseContext.current.id', $default->id)
            ->has('warehouseContext.available', 2));
});

test('the warehouse chosen in the selector is the current one on the next page', function () {
    $admin = userWithRole(SystemRole::Admin);
    $chosen = Warehouse::factory()->create(['name' => 'Bodega A']);
    $default = Warehouse::factory()->create(['name' => 'Bodega B']);
    $admin->warehouses()->attach($default->id, ['is_default' => true]);

    $this->actingAs($admin)
        ->post(route('warehouses.set-current'), ['warehouse_id' => $chosen->id])
        ->assertRedirect();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('warehouseContext.current.id', $chosen->id));
});

test('guests get no warehouse context', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('warehouseContext', null));
});
