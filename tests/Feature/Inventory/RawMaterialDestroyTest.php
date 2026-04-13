<?php

use App\Models\InventoryBatch;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

describe('Raw Material Destroy', function () {
    beforeEach(function () {
        if (Role::count() === 0) {
            Role::create(['name' => 'admin']);
            Role::create(['name' => 'produccion']);
            Role::create(['name' => 'comercial']);
        }

        $this->unit = UnitOfMeasure::create([
            'code' => 'kg',
            'name' => 'Kilogramo',
            'symbol' => 'kg',
        ]);

        $this->rawMaterial = RawMaterial::create([
            'code' => 'MP001',
            'unit_of_measure_id' => $this->unit->id,
            'current_price' => 100.00,
            'minimum_stock' => 10,
            'alert_days_before_expiry' => 30,
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    });

    it('allows admin to delete raw material without batches', function () {
        $response = $this->actingAs($this->admin)
            ->delete(route('raw-materials.destroy', $this->rawMaterial));

        $response->assertRedirect(route('raw-materials.index'));
        $this->assertTrue($this->rawMaterial->fresh()->trashed());
    });

    it('blocks deletion if raw material has batches with remaining_quantity > 0', function () {
        // Crear un batch con stock disponible
        InventoryBatch::create([
            'raw_material_id' => $this->rawMaterial->id,
            'initial_quantity' => 100,
            'remaining_quantity' => 50, // > 0
            'unit_price' => 10.00,
            'entry_date' => now()->subDays(10),
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('raw-materials.destroy', $this->rawMaterial));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Verificar que NO fue eliminado
        $this->assertDatabaseHas('raw_materials', [
            'id' => $this->rawMaterial->id,
            'deleted_at' => null,
        ]);
    });

    it('allows deletion if all batches have remaining_quantity = 0', function () {
        // Crear batches agotados
        InventoryBatch::create([
            'raw_material_id' => $this->rawMaterial->id,
            'initial_quantity' => 100,
            'remaining_quantity' => 0, // Agotado
            'unit_price' => 10.00,
            'entry_date' => now()->subDays(30),
        ]);

        InventoryBatch::create([
            'raw_material_id' => $this->rawMaterial->id,
            'initial_quantity' => 50,
            'remaining_quantity' => 0, // Agotado
            'unit_price' => 12.00,
            'entry_date' => now()->subDays(15),
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('raw-materials.destroy', $this->rawMaterial));

        $response->assertRedirect(route('raw-materials.index'));

        // Verificar soft delete
        $this->assertTrue($this->rawMaterial->fresh()->trashed());
    });

    it('blocks deletion if at least one batch has remaining_quantity > 0', function () {
        // Batch agotado
        InventoryBatch::create([
            'raw_material_id' => $this->rawMaterial->id,
            'initial_quantity' => 100,
            'remaining_quantity' => 0,
            'unit_price' => 10.00,
            'entry_date' => now()->subDays(30),
        ]);

        // Batch con stock
        InventoryBatch::create([
            'raw_material_id' => $this->rawMaterial->id,
            'initial_quantity' => 50,
            'remaining_quantity' => 25, // > 0
            'unit_price' => 12.00,
            'entry_date' => now()->subDays(15),
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('raw-materials.destroy', $this->rawMaterial));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Verificar que NO fue eliminado
        $this->assertDatabaseHas('raw_materials', [
            'id' => $this->rawMaterial->id,
            'deleted_at' => null,
        ]);
    });

    it('forbids non-admin to delete raw materials', function () {
        $user = User::factory()->create();
        $user->assignRole('produccion');

        $response = $this->actingAs($user)
            ->delete(route('raw-materials.destroy', $this->rawMaterial));

        $response->assertForbidden();
    });
});