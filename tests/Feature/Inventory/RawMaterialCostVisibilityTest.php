<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\InventoryBatch;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $unit = UnitOfMeasure::create(['code' => 'KG-COST', 'name' => 'Kilogramo', 'symbol' => 'kg']);

    $this->rawMaterial = RawMaterial::create([
        'code' => 'MP-COST-001',
        'unit_of_measure_id' => $unit->id,
        'current_price' => 12,
        'previous_price' => 10,
        'minimum_stock' => 5,
        'alert_days_before_expiry' => 30,
        'is_active' => true,
    ]);

    $warehouse = Warehouse::create([
        'name' => 'Planta',
        'city' => 'Cali',
        'type' => 'factory',
        'is_active' => true,
    ]);

    InventoryBatch::create([
        'raw_material_id' => $this->rawMaterial->id,
        'warehouse_id' => $warehouse->id,
        'lot_number' => 'L-COST-01',
        'initial_quantity' => 100,
        'remaining_quantity' => 80,
        'unit_price' => 11.5,
        'entry_date' => now()->toDateString(),
    ]);
});

it('hides raw material and batch prices from users without costs.view', function (): void {
    // Matriz, principio 1: en materias primas el precio es costo.
    $this->actingAs(userWithRole(SystemRole::Production))
        ->get(route('raw-materials.show', $this->rawMaterial))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/RawMaterials/Show')
            ->where('can.viewCosts', false)
            ->where('rawMaterial.code', 'MP-COST-001')
            ->where('rawMaterial.inventory_batches.0.lot_number', 'L-COST-01')
            ->where('rawMaterial.inventory_batches.0.remaining_quantity', '80.0000')
            ->missing('rawMaterial.current_price')
            ->missing('rawMaterial.previous_price')
            ->missing('rawMaterial.inventory_batches.0.unit_price'));
});

it('shows raw material and batch prices to users with costs.view', function (): void {
    $this->actingAs(userWithRole(SystemRole::Admin))
        ->get(route('raw-materials.show', $this->rawMaterial))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.viewCosts', true)
            ->has('rawMaterial.current_price')
            ->has('rawMaterial.previous_price')
            ->where('rawMaterial.inventory_batches.0.unit_price', '11.5000'));
});

it('never sends prices to the raw material edit form, even to an editor without costs.view', function (): void {
    // Un rol creado desde la pantalla de roles (2.4) podría editar materias primas sin ver costos.
    $role = Role::create(['name' => 'inventario-sin-costos', 'guard_name' => 'web']);
    $role->syncPermissions([Permission::RawMaterialsView->value, Permission::RawMaterialsEdit->value]);

    $editor = User::factory()->create();
    $editor->assignRole($role);

    $this->actingAs($editor)
        ->get(route('raw-materials.edit', $this->rawMaterial))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/RawMaterials/Edit')
            ->where('rawMaterial.code', 'MP-COST-001')
            ->where('rawMaterial.minimum_stock', '5.0000')
            ->missing('rawMaterial.current_price')
            ->missing('rawMaterial.previous_price'));
});

it('keeps the raw material edit form working for admin', function (): void {
    $this->actingAs(userWithRole(SystemRole::Admin))
        ->get(route('raw-materials.edit', $this->rawMaterial))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rawMaterial.id', $this->rawMaterial->id)
            ->where('rawMaterial.unit_of_measure_id', $this->rawMaterial->unit_of_measure_id)
            ->where('rawMaterial.alert_days_before_expiry', 30)
            ->where('rawMaterial.is_active', true));
});
