<?php

declare(strict_types=1);

use App\Enums\ProductionOrderStatus;
use App\Enums\SystemRole;
use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderLineAdjustment;
use App\Models\ProductionOrderPackagingPlan;
use App\Models\ProductionRemnant;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Gate;

// Auditoría 3.6, PR 6: lo que escribe en una OP abierta bloquea la fila y vuelve a preguntar a la policy con el estado
// bloqueado (OPL-02, OPX-03, C10). La carrera se simula sin procesos: justo después de que la policy aprueba la petición,
// otra «petición» cambia el estado de la OP, que es lo que ve una escritura que esperó al bloqueo de completar.

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(SystemRole::Admin->value);
    $this->actingAs($this->admin);

    $unit = UnitOfMeasure::factory()->create();
    $this->warehouse = Warehouse::factory()->factory()->create();
    $this->product = Product::factory()->create([
        'category_id' => ProductCategory::factory()->create()->id,
        'unit_of_measure_id' => $unit->id,
    ]);
    $formula = Formula::factory()->create(['product_id' => $this->product->id, 'created_by' => $this->admin->id]);
    $this->rawMaterial = RawMaterial::factory()->create(['unit_of_measure_id' => $unit->id]);

    $variant = fn (string $value) => ProductVariant::factory()->create([
        'product_id' => $this->product->id,
        'presentation_value' => $value,
        'package_raw_material_id' => null,
        'label_raw_material_id' => null,
    ]);
    $this->gallon = $variant('1');
    $this->bucket = $variant('5');

    $this->order = ProductionOrder::factory()->inProgress()->create([
        'product_id' => $this->product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 10,
    ]);
    $this->gallonPlan = ProductionOrderPackagingPlan::createForVariant((int) $this->order->id, (int) $this->gallon->id, '10');
    $this->adjustment = ProductionOrderLineAdjustment::create([
        'production_order_id' => $this->order->id, 'raw_material_id' => $this->rawMaterial->id,
        'quantity' => '1', 'reason' => 'Ajuste', 'created_by' => $this->admin->id,
    ]);
});

/**
 * Cambia el estado de la OP en la base apenas la policy aprueba la petición: la escritura llega después, como cuando
 * esperó a que otra petición soltara el bloqueo.
 */
function changeOrderStatusRightAfterAuthorization(ProductionOrder $order, ProductionOrderStatus $status): void
{
    $changed = false;

    Gate::after(function (User $user, string $ability, ?bool $result, array $arguments) use ($order, $status, &$changed): void {
        $target = $arguments[0] ?? null;

        if ($changed || $result !== true || ! $target instanceof ProductionOrder || $target->id !== $order->id) {
            return;
        }

        $changed = true;
        ProductionOrder::query()->whereKey($order->id)->update(['status' => $status]);
    });
}

it('no agrega una presentación si la orden se completó mientras esperaba (OPL-02)', function () {
    changeOrderStatusRightAfterAuthorization($this->order, ProductionOrderStatus::Completed);

    $this->post(route('production-orders.packaging-plans.store', $this->order), [
        'product_variant_id' => $this->bucket->id,
        'planned_units' => 2,
    ])->assertSessionHas('error', "No se puede agregar una presentación a una orden en estado 'Completada'.")
        // Como error de validación también: Inertia llama a onError y el formulario conserva lo escrito.
        ->assertSessionHasErrors(['production_order' => "No se puede agregar una presentación a una orden en estado 'Completada'."]);

    expect(ProductionOrderPackagingPlan::query()->where('production_order_id', $this->order->id)->count())->toBe(1);
});

it('no quita una presentación si la orden se completó mientras esperaba', function () {
    changeOrderStatusRightAfterAuthorization($this->order, ProductionOrderStatus::Completed);

    $this->delete(route('production-orders.packaging-plans.destroy', [$this->order, $this->gallonPlan]))
        ->assertSessionHas('error', "No se puede quitar una presentación de una orden en estado 'Completada'.")
        // Como error de validación también: Inertia llama a onError y el formulario conserva lo escrito.
        ->assertSessionHasErrors(['production_order' => "No se puede quitar una presentación de una orden en estado 'Completada'."]);

    expect($this->gallonPlan->fresh())->not->toBeNull();
});

it('no agrega un ajuste de línea si la orden se completó mientras esperaba (OPX-03)', function () {
    changeOrderStatusRightAfterAuthorization($this->order, ProductionOrderStatus::Completed);

    $this->post(route('production-orders.line-adjustments.store', $this->order), [
        'raw_material_id' => $this->rawMaterial->id,
        'quantity' => '1.5',
        'reason' => 'Corrección de viscosidad',
    ])->assertSessionHas('error', "No se puede agregar un ajuste de línea a una orden en estado 'Completada'.")
        // Como error de validación también: Inertia llama a onError y el formulario conserva lo escrito.
        ->assertSessionHasErrors(['production_order' => "No se puede agregar un ajuste de línea a una orden en estado 'Completada'."]);

    expect(ProductionOrderLineAdjustment::query()->where('production_order_id', $this->order->id)->count())->toBe(1);
});

it('no quita un ajuste de línea si la orden se completó mientras esperaba (OPX-03)', function () {
    changeOrderStatusRightAfterAuthorization($this->order, ProductionOrderStatus::Completed);

    $this->delete(route('production-orders.line-adjustments.destroy', [$this->order, $this->adjustment]))
        ->assertSessionHas('error', "No se puede quitar un ajuste de línea de una orden en estado 'Completada'.")
        // Como error de validación también: Inertia llama a onError y el formulario conserva lo escrito.
        ->assertSessionHasErrors(['production_order' => "No se puede quitar un ajuste de línea de una orden en estado 'Completada'."]);

    expect($this->adjustment->fresh())->not->toBeNull();
});

it('un operario no agrega una presentación si la orden pasó a revisión mientras esperaba (C10)', function () {
    $operator = User::factory()->create();
    $operator->assignRole(SystemRole::Operator->value);
    $this->actingAs($operator);
    changeOrderStatusRightAfterAuthorization($this->order, ProductionOrderStatus::PendingReview);

    $this->post(route('production-orders.packaging-plans.store', $this->order), [
        'product_variant_id' => $this->bucket->id,
        'planned_units' => 2,
    ])->assertSessionHas('error', "No se puede agregar una presentación a una orden en estado 'Pendiente de revisión'.")
        // Como error de validación también: Inertia llama a onError y el formulario conserva lo escrito.
        ->assertSessionHasErrors(['production_order' => "No se puede agregar una presentación a una orden en estado 'Pendiente de revisión'."]);

    expect(ProductionOrderPackagingPlan::query()->where('production_order_id', $this->order->id)->count())->toBe(1);
});

it('quien puede revisar sí agrega una presentación a una orden que pasó a revisión', function () {
    changeOrderStatusRightAfterAuthorization($this->order, ProductionOrderStatus::PendingReview);

    $this->post(route('production-orders.packaging-plans.store', $this->order), [
        'product_variant_id' => $this->bucket->id,
        'planned_units' => 2,
    ])->assertSessionHasNoErrors()->assertSessionMissing('error');

    expect(ProductionOrderPackagingPlan::query()->where('production_order_id', $this->order->id)->count())->toBe(2);
});

it('no consume un saldo si la orden se completó mientras esperaba', function () {
    $source = ProductionOrder::factory()->completed()->create([
        'product_id' => $this->product->id, 'formula_id' => $this->order->formula_id, 'warehouse_id' => $this->warehouse->id,
    ]);
    $remnant = ProductionRemnant::factory()->create([
        'source_order_id' => $source->id, 'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
        'created_by' => $this->admin->id,
    ]);
    changeOrderStatusRightAfterAuthorization($this->order, ProductionOrderStatus::Completed);

    $this->post(route('production-orders.consume-remnant', $this->order), [
        'remnant_id' => $remnant->id,
        'quantity_gallons' => '1',
    ])->assertSessionHas('error', "No se puede consumir un saldo en una orden en estado 'Completada'.")
        // Como error de validación también: Inertia llama a onError y el formulario conserva lo escrito.
        ->assertSessionHasErrors(['production_order' => "No se puede consumir un saldo en una orden en estado 'Completada'."]);

    expect((string) $remnant->fresh()->available_quantity_gallons)->toBe('10.0000');
});

it('no inicia, no devuelve ni cambia el color si el estado cambió mientras esperaba', function (string $route, array $body, ProductionOrderStatus $from, string $message, bool $fromDialog) {
    $this->order->update(['status' => $from]);
    changeOrderStatusRightAfterAuthorization($this->order, ProductionOrderStatus::Cancelled);

    $response = $this->from(route('production-orders.show', $this->order))
        ->call($route === 'production-orders.update-color' ? 'PATCH' : 'POST', route($route, $this->order), $body)
        ->assertSessionHas('error', $message);

    // Desde un diálogo, también error de validación: no se cierra como si se hubiera guardado ni borra lo escrito.
    $fromDialog
        ? $response->assertSessionHasErrors(['production_order' => $message])
        : $response->assertSessionHasNoErrors();

    expect($this->order->fresh()->status)->toBe(ProductionOrderStatus::Cancelled);
})->with([
    'iniciar' => ['production-orders.start', [], ProductionOrderStatus::Pending, "No se puede iniciar producción de una orden en estado 'Cancelada'.", false],
    'devolver a planta' => ['production-orders.reject-review', ['reason' => 'Falta la medición de viscosidad'], ProductionOrderStatus::PendingReview, "Solo se pueden devolver órdenes en estado 'Pendiente de revisión'.", true],
    'color' => ['production-orders.update-color', ['color' => 'Rojo'], ProductionOrderStatus::InProgress, "No se puede cambiar el color de una orden en estado 'Cancelada'.", true],
]);
