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

// Auditoría 3.6, PR 10 (BOD-01, BOD-03): cambiar de bodega es para todo usuario autenticado, pero solo entre las que tiene
// disponibles (las asignadas, o todas con `warehouses.view_all`; docs/MATRIZ_RBAC.md §5).

test('an operator can switch to a warehouse assigned to them', function () {
    $operator = userWithRole(SystemRole::Operator);
    $first = Warehouse::factory()->create(['name' => 'Bodega A']);
    $second = Warehouse::factory()->create(['name' => 'Bodega B']);
    $operator->warehouses()->attach($first->id, ['is_default' => true]);
    $operator->warehouses()->attach($second->id);

    $this->actingAs($operator)
        ->post(route('warehouses.set-current'), ['warehouse_id' => $second->id])
        ->assertRedirect()
        ->assertInertiaFlash('success', 'Bodega activa actualizada.')
        ->assertSessionHas('current_warehouse_id', $second->id);
});

test('an operator cannot switch to a warehouse that is not assigned to them', function () {
    $operator = userWithRole(SystemRole::Operator);
    $mine = Warehouse::factory()->create(['name' => 'Bodega A']);
    $other = Warehouse::factory()->create(['name' => 'Bodega B']);
    $operator->warehouses()->attach($mine->id, ['is_default' => true]);

    $this->actingAs($operator)
        ->post(route('warehouses.set-current'), ['warehouse_id' => $other->id])
        ->assertRedirect()
        ->assertInertiaFlash('error', 'No tienes acceso a la bodega seleccionada.')
        ->assertInertiaFlashMissing('success');

    expect(session('current_warehouse_id'))->not->toBe($other->id);
});

test('an inactive warehouse is refused instead of answering that it was updated', function () {
    $admin = userWithRole(SystemRole::Admin);
    $active = Warehouse::factory()->create(['name' => 'Bodega A']);
    $inactive = Warehouse::factory()->create(['name' => 'Bodega B', 'is_active' => false]);
    $admin->warehouses()->attach($active->id, ['is_default' => true]);

    $this->actingAs($admin)
        ->post(route('warehouses.set-current'), ['warehouse_id' => $inactive->id])
        ->assertRedirect()
        ->assertInertiaFlash('error', 'No tienes acceso a la bodega seleccionada.')
        ->assertInertiaFlashMissing('success');
});
