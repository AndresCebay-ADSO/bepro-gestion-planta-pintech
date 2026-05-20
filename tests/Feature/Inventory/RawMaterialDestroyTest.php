<?php

use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/**
 * @property Warehouse $warehouse
 * @property UnitOfMeasure $unit
 * @property RawMaterial $rawMaterial
 * @property User $admin
 */
uses(RefreshDatabase::class);

describe('Raw Material Destroy', function () {
    beforeEach(function () {
        if (Role::count() === 0) {
            Role::create(['name' => 'admin']);
            Role::create(['name' => 'produccion']);
            Role::create(['name' => 'comercial']);
        }

        $this->warehouse = Warehouse::create([
            'name' => 'Test Warehouse',
            'city' => 'Test City',
            'type' => 'factory',
        ]);

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

    it('allows admin to physically delete raw material without activity', function () {
        $response = $this->actingAs($this->admin)
            ->delete(route('raw-materials.destroy', $this->rawMaterial));

        $response->assertRedirect(route('raw-materials.index'));
        $response->assertSessionHas('success', 'Materia prima eliminada físicamente exitosamente.');
        $this->assertDatabaseMissing('raw_materials', ['id' => $this->rawMaterial->id]);
    });

    it('blocks deletion if raw material has batches with remaining_quantity > 0', function () {
        // Crear un batch con stock disponible
        InventoryBatch::create([
            'raw_material_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouse->id,
            'initial_quantity' => 100,
            'remaining_quantity' => 50, // > 0
            'unit_price' => 10.00,
            'entry_date' => now()->subDays(10),
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('raw-materials.destroy', $this->rawMaterial));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Verificar que NO fue eliminado ni desactivado
        $this->assertDatabaseHas('raw_materials', [
            'id' => $this->rawMaterial->id,
            'is_active' => true,
        ]);
    });

    it('allows deactivation if all batches have remaining_quantity = 0', function () {
        // Crear batches agotados
        InventoryBatch::create([
            'raw_material_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouse->id,
            'initial_quantity' => 100,
            'remaining_quantity' => 0, // Agotado
            'unit_price' => 10.00,
            'entry_date' => now()->subDays(30),
        ]);

        InventoryBatch::create([
            'raw_material_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouse->id,
            'initial_quantity' => 50,
            'remaining_quantity' => 0, // Agotado
            'unit_price' => 12.00,
            'entry_date' => now()->subDays(15),
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('raw-materials.destroy', $this->rawMaterial));

        $response->assertRedirect(route('raw-materials.index'));

        // Verificar desactivación
        $this->assertFalse((bool) $this->rawMaterial->fresh()->is_active);
    });

    it('blocks deletion if at least one batch has remaining_quantity > 0', function () {
        // Batch agotado
        InventoryBatch::create([
            'raw_material_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouse->id,
            'initial_quantity' => 100,
            'remaining_quantity' => 0,
            'unit_price' => 10.00,
            'entry_date' => now()->subDays(30),
        ]);

        // Batch con stock
        InventoryBatch::create([
            'raw_material_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouse->id,
            'initial_quantity' => 50,
            'remaining_quantity' => 25, // > 0
            'unit_price' => 12.00,
            'entry_date' => now()->subDays(15),
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('raw-materials.destroy', $this->rawMaterial));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Verificar que NO fue eliminado ni desactivado
        $this->assertDatabaseHas('raw_materials', [
            'id' => $this->rawMaterial->id,
            'is_active' => true,
        ]);
    });

    it('forbids non-admin to delete raw materials', function () {
        $user = User::factory()->create();
        $user->assignRole('produccion');

        $response = $this->actingAs($user)
            ->delete(route('raw-materials.destroy', $this->rawMaterial));

        $response->assertForbidden();
    });

    it('allows deactivation when raw material has inventory movements', function () {
        InventoryMovement::create([
            'raw_material_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'entry',
            'quantity' => 100,
            'cost_price' => 10.00,
            'movement_date' => now()->subDays(10),
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('raw-materials.destroy', $this->rawMaterial));

        $response->assertRedirect(route('raw-materials.index'));
        $response->assertSessionHas('success', 'Materia prima desactivada exitosamente (conserva historial).');
        $this->assertFalse((bool) $this->rawMaterial->fresh()->is_active);
    });

    it('allows deactivation when raw material has formula details', function () {
        $productCategory = ProductCategory::create(['name' => 'Test Category']);

        $product = Product::create([
            'code' => 'PROD001',
            'name' => 'Test Product',
            'category_id' => $productCategory->id,
            'unit_of_measure_id' => $this->unit->id,
            'is_active' => true,
        ]);

        $formula = Formula::create([
            'product_id' => $product->id,
            'version' => 1,
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        FormulaDetail::create([
            'formula_id' => $formula->id,
            'raw_material_id' => $this->rawMaterial->id,
            'quantity' => 50,
            'unit_of_measure_id' => $this->unit->id,
            'step_order' => 1,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('raw-materials.destroy', $this->rawMaterial));

        $response->assertRedirect(route('raw-materials.index'));
        $response->assertSessionHas('success', 'Materia prima desactivada exitosamente (conserva historial).');
        $this->assertFalse((bool) $this->rawMaterial->fresh()->is_active);
    });

    it('blocks deletion when raw material has production order details', function () {
        $productCategory = ProductCategory::create(['name' => 'Test Category']);

        $product = Product::create([
            'code' => 'PROD002',
            'name' => 'Test Product 2',
            'category_id' => $productCategory->id,
            'unit_of_measure_id' => $this->unit->id,
            'is_active' => true,
        ]);

        $formula = Formula::create([
            'product_id' => $product->id,
            'version' => 1,
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $productionOrder = ProductionOrder::create([
            'order_number' => 'OP-001',
            'product_id' => $product->id,
            'formula_id' => $formula->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
            'status' => 'pending',
            'planned_date' => now()->addDays(5),
            'created_by' => $this->admin->id,
        ]);

        ProductionOrderDetail::create([
            'production_order_id' => $productionOrder->id,
            'raw_material_id' => $this->rawMaterial->id,
            'step_order' => 1,
            'planned_quantity' => 50,
            'unit_cost' => 10.00,
            'total_cost' => 500.00,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('raw-materials.destroy', $this->rawMaterial));

        $response->assertRedirect(route('raw-materials.index'));
        $response->assertSessionHas('success', 'Materia prima desactivada exitosamente (conserva historial).');
        $this->assertFalse((bool) $this->rawMaterial->fresh()->is_active);
    });

    it('blocks action when raw material is already inactive', function () {
        InventoryMovement::create([
            'raw_material_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'entry',
            'quantity' => 100,
            'cost_price' => 10.00,
            'movement_date' => now()->subDays(10),
            'created_by' => $this->admin->id,
        ]);

        $this->rawMaterial->update(['is_active' => false]);

        $response = $this->actingAs($this->admin)
            ->delete(route('raw-materials.destroy', $this->rawMaterial));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('raw_materials', [
            'id' => $this->rawMaterial->id,
            'is_active' => false,
        ]);
    });
});
