<?php

use App\Enums\ProductionOrderStatus;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderLineAdjustment;
use App\Models\RawMaterial;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->user);
    $this->rawMaterial = RawMaterial::factory()->create(['is_active' => true]);
    $this->productionOrder = ProductionOrder::factory()->create([
        'status' => ProductionOrderStatus::Pending,
        'warehouse_id' => 1, // Adjust as per your factory setup
    ]);
});

test('can add a line adjustment to a pending order', function () {
    $data = [
        'raw_material_id' => $this->rawMaterial->id,
        'quantity' => 5.5,
        'reason' => 'Viscosity correction',
        'notes' => 'Some extra notes',
    ];

    $response = $this->post(route('production-orders.line-adjustments.store', $this->productionOrder), $data);

    $response->assertRedirect();
    $this->assertDatabaseHas('production_order_line_adjustments', [
        'production_order_id' => $this->productionOrder->id,
        'raw_material_id' => $this->rawMaterial->id,
        'quantity' => 5.5,
        'reason' => 'Viscosity correction',
        'created_by' => $this->user->id,
    ]);
});

test('cannot add a line adjustment to a completed order', function () {
    $this->productionOrder->update(['status' => ProductionOrderStatus::Completed]);

    $data = [
        'raw_material_id' => $this->rawMaterial->id,
        'quantity' => 5.5,
        'reason' => 'Viscosity correction',
    ];

    $response = $this->post(route('production-orders.line-adjustments.store', $this->productionOrder), $data);

    $response->assertSessionHasErrors(['production_order']);
    $this->assertDatabaseEmpty('production_order_line_adjustments');
});

test('can delete a line adjustment from a pending order', function () {
    $adjustment = ProductionOrderLineAdjustment::create([
        'production_order_id' => $this->productionOrder->id,
        'raw_material_id' => $this->rawMaterial->id,
        'quantity' => 5.5,
        'reason' => 'Test',
        'created_by' => $this->user->id,
    ]);

    $response = $this->delete(route('production-orders.line-adjustments.destroy', [
        'order' => $this->productionOrder->id,
        'adjustment' => $adjustment->id,
    ]));

    $response->assertRedirect();
    $this->assertDatabaseMissing('production_order_line_adjustments', ['id' => $adjustment->id]);
});

test('cannot delete a line adjustment from a completed order', function () {
    $adjustment = ProductionOrderLineAdjustment::create([
        'production_order_id' => $this->productionOrder->id,
        'raw_material_id' => $this->rawMaterial->id,
        'quantity' => 5.5,
        'reason' => 'Test',
        'created_by' => $this->user->id,
    ]);

    $this->productionOrder->update(['status' => ProductionOrderStatus::Completed]);

    $response = $this->delete(route('production-orders.line-adjustments.destroy', [
        'order' => $this->productionOrder->id,
        'adjustment' => $adjustment->id,
    ]));

    $response->assertRedirect();
    $this->assertDatabaseHas('production_order_line_adjustments', ['id' => $adjustment->id]);
});

test('validates required fields for line adjustment', function () {
    $response = $this->post(route('production-orders.line-adjustments.store', $this->productionOrder), []);

    $response->assertSessionHasErrors(['raw_material_id', 'quantity', 'reason']);
});
