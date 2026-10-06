<?php

declare(strict_types=1);

use App\Enums\AlertType;
use App\Enums\InventoryMovementType;
use App\Enums\ProductionOrderStatus;
use App\Enums\SystemRole;
use App\Models\Alert;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\ProductionOrder;
use App\Models\RawMaterial;
use App\Models\ShrinkWrap;
use App\Models\ShrinkWrapType;
use App\Models\ShrinkWrapTypeItem;
use App\Models\Warehouse;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;

// Registro de termoencogido (3.8): el operario elige una OP completada, un tipo y las aplicaciones; al guardar se
// descuenta receta × aplicaciones de la bodega de la OP, por FIFO, como gasto general (decisiones del 2026-09-29,
// 2026-10-02 y 2026-10-05).

beforeEach(function () {
    // 15:00 UTC = 10:00 en Bogotá: el mismo día en los dos husos.
    $this->travelTo(CarbonImmutable::parse('2026-10-05 15:00:00', 'UTC'));

    $this->warehouse = Warehouse::factory()->factory()->create(['name' => 'Fábrica Cali']);
    $this->order = ProductionOrder::factory()->create([
        'order_number' => 'OP-2026-0042',
        'lot_number' => 1042,
        'warehouse_id' => $this->warehouse->id,
        'status' => ProductionOrderStatus::Completed,
        'completion_date' => '2026-10-01',
    ]);

    $this->bag = RawMaterial::factory()->secondaryPackaging()->create(['code' => 'BOLSA-1', 'minimum_stock' => 0]);
    $this->tray = RawMaterial::factory()->secondaryPackaging()->create(['code' => 'BANDEJA-1', 'minimum_stock' => 0]);

    // FIFO: el lote más antiguo de bolsas se agota primero.
    $this->oldBags = InventoryBatch::factory()->create([
        'raw_material_id' => $this->bag->id, 'warehouse_id' => $this->warehouse->id,
        'initial_quantity' => 5, 'remaining_quantity' => 5, 'unit_price' => 100, 'entry_date' => '2026-09-01',
    ]);
    $this->newBags = InventoryBatch::factory()->create([
        'raw_material_id' => $this->bag->id, 'warehouse_id' => $this->warehouse->id,
        'initial_quantity' => 100, 'remaining_quantity' => 100, 'unit_price' => 200, 'entry_date' => '2026-09-15',
    ]);
    $this->trays = InventoryBatch::factory()->create([
        'raw_material_id' => $this->tray->id, 'warehouse_id' => $this->warehouse->id,
        'initial_quantity' => 100, 'remaining_quantity' => 100, 'unit_price' => 50, 'entry_date' => '2026-09-01',
    ]);

    $this->type = ShrinkWrapType::factory()->create(['name' => 'Galón']);
    ShrinkWrapTypeItem::factory()->create(['shrink_wrap_type_id' => $this->type->id, 'raw_material_id' => $this->bag->id, 'quantity' => '1']);
    ShrinkWrapTypeItem::factory()->create(['shrink_wrap_type_id' => $this->type->id, 'raw_material_id' => $this->tray->id, 'quantity' => '1']);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function shrinkWrapPayload(array $overrides = []): array
{
    return array_merge([
        'production_order_id' => test()->order->id,
        'shrink_wrap_type_id' => test()->type->id,
        'applications' => 8,
        'wrapped_at' => '2026-10-05',
        'notes' => null,
    ], $overrides);
}

it('descuenta receta × aplicaciones por FIFO de la bodega de la OP', function () {
    actingAsRole(SystemRole::Operator);

    // Termoencogido el 2 y registrado hoy (5): las salidas llevan la fecha del registro, el registro la del termoencogido.
    $response = $this->post(route('production.shrink-wraps.store'), shrinkWrapPayload(['wrapped_at' => '2026-10-02']))
        ->assertSessionHasNoErrors();

    $shrinkWrap = ShrinkWrap::query()->sole();
    $response->assertRedirect(route('production.shrink-wraps.show', $shrinkWrap));

    // Bolsas: 5 × 100 + 3 × 200 = 1.100. Bandejas: 8 × 50 = 400.
    expect($shrinkWrap->total_cost)->toBe('1500.0000')
        ->and($shrinkWrap->wrapped_at->toDateString())->toBe('2026-10-02')
        ->and($shrinkWrap->warehouse_id)->toBe($this->warehouse->id)
        ->and($shrinkWrap->items()->where('raw_material_id', $this->bag->id)->value('quantity'))->toBe('8.0000')
        ->and($shrinkWrap->items()->where('raw_material_id', $this->bag->id)->value('total_cost'))->toBe('1100.0000')
        ->and($this->oldBags->fresh()->remaining_quantity)->toBe('0.0000')
        ->and($this->newBags->fresh()->remaining_quantity)->toBe('97.0000')
        ->and($this->trays->fresh()->remaining_quantity)->toBe('92.0000');

    $movements = InventoryMovement::query()->where('shrink_wrap_id', $shrinkWrap->id)->get();

    expect($movements)->toHaveCount(3)
        ->and($movements->every(fn (InventoryMovement $movement): bool => $movement->type === InventoryMovementType::Exit
            && $movement->production_order_id === null
            && $movement->movement_date->toDateString() === '2026-10-05'))->toBeTrue()
        ->and($movements->first()->notes)->toBe("Consumo FIFO en termoencogido #{$shrinkWrap->id} (OP #OP-2026-0042)");
});

it('no carga el termoencogido a la OP: su costo es gasto general', function () {
    actingAsRole(SystemRole::Operator);

    $this->post(route('production.shrink-wraps.store'), shrinkWrapPayload())->assertSessionHasNoErrors();

    expect(InventoryMovement::query()->where('production_order_id', $this->order->id)->exists())->toBeFalse();
});

it('no guarda nada si falta stock de alguna materia prima de la receta', function () {
    actingAsRole(SystemRole::Operator);

    $this->post(route('production.shrink-wraps.store'), shrinkWrapPayload(['applications' => 200]))
        ->assertSessionHasErrors(['applications' => "Stock insuficiente de empaque secundario 'BOLSA-1' en la bodega de la orden. Requerido: 200.0000, faltante: 95.0000."]);

    expect(ShrinkWrap::query()->exists())->toBeFalse()
        ->and(InventoryMovement::query()->exists())->toBeFalse()
        ->and($this->oldBags->fresh()->remaining_quantity)->toBe('5.0000')
        ->and($this->trays->fresh()->remaining_quantity)->toBe('100.0000');
});

it('no ofrece ni acepta una OP completada hace más de 6 meses', function () {
    actingAsRole(SystemRole::Operator);
    // Hoy es 2026-10-05: la ventana empieza el 2026-04-05.
    $old = ProductionOrder::factory()->create([
        'order_number' => 'OP-2026-0001',
        'status' => ProductionOrderStatus::Completed,
        'completion_date' => '2026-04-04',
    ]);
    ProductionOrder::factory()->create([
        'order_number' => 'OP-2026-0002',
        'status' => ProductionOrderStatus::Completed,
        'completion_date' => '2026-04-05',
    ]);

    $this->get(route('production.shrink-wraps.create'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('orderOptions', fn ($orders) => collect($orders)->pluck('order_number')->sort()->values()->all() === ['OP-2026-0002', 'OP-2026-0042']));

    $this->post(route('production.shrink-wraps.store'), shrinkWrapPayload(['production_order_id' => $old->id]))
        ->assertSessionHasErrors(['production_order_id' => 'La orden de producción debe estar completada y en los últimos 6 meses.']);
});

it('rechaza un costo que no cabe en el registro sin llegar a la base', function () {
    actingAsRole(SystemRole::Operator);
    // Sin control de inventario no hay stock que lo frene: 99.999.999 × 200 = 2×10¹⁰, más que decimal(14,4).
    $untracked = RawMaterial::factory()->secondaryPackaging()->withoutInventoryTracking()->create(['code' => 'BOLSA-X', 'current_price' => 200]);
    $type = ShrinkWrapType::factory()->create();
    ShrinkWrapTypeItem::factory()->create(['shrink_wrap_type_id' => $type->id, 'raw_material_id' => $untracked->id, 'quantity' => '1']);

    $this->post(route('production.shrink-wraps.store'), shrinkWrapPayload(['shrink_wrap_type_id' => $type->id, 'applications' => 99999999]))
        ->assertSessionHasErrors(['applications' => 'Son demasiadas aplicaciones: el costo de BOLSA-X no cabe en el registro.']);

    expect(ShrinkWrap::query()->exists())->toBeFalse()
        ->and(InventoryMovement::query()->exists())->toBeFalse();
});

it('costea al precio de referencia una bolsa sin control de inventario', function () {
    actingAsRole(SystemRole::Operator);
    $untracked = RawMaterial::factory()->secondaryPackaging()->withoutInventoryTracking()->create(['current_price' => 300]);
    $type = ShrinkWrapType::factory()->create();
    ShrinkWrapTypeItem::factory()->create(['shrink_wrap_type_id' => $type->id, 'raw_material_id' => $untracked->id, 'quantity' => '1']);

    $this->post(route('production.shrink-wraps.store'), shrinkWrapPayload(['shrink_wrap_type_id' => $type->id, 'applications' => 2]))
        ->assertSessionHasNoErrors();

    $movement = InventoryMovement::query()->sole();

    expect($movement->batch_id)->toBeNull()
        ->and($movement->notes)->toStartWith('Consumo sin control de inventario en termoencogido')
        ->and(ShrinkWrap::query()->sole()->total_cost)->toBe('600.0000');
});

it('guarda la receta con que se registró: editar el tipo después no cambia el historial', function () {
    actingAsRole(SystemRole::Operator);
    $this->post(route('production.shrink-wraps.store'), shrinkWrapPayload());

    ShrinkWrapTypeItem::query()->where('raw_material_id', $this->bag->id)->update(['quantity' => '3']);

    expect(ShrinkWrap::query()->sole()->items()->where('raw_material_id', $this->bag->id)->value('quantity_per_application'))
        ->toBe('1.0000');
});

it('audita el registro', function () {
    actingAsRole(SystemRole::Operator);
    $this->post(route('production.shrink-wraps.store'), shrinkWrapPayload());

    $shrinkWrap = ShrinkWrap::query()->sole();

    expect(Activity::query()->where('subject_type', ShrinkWrap::class)->where('subject_id', $shrinkWrap->id)->pluck('description')->all())
        ->toBe(["Termoencogido \"{$shrinkWrap->id}\" creado"]);
});

it('avisa con una alerta cuando el termoencogido deja una materia prima bajo su mínimo', function () {
    actingAsRole(SystemRole::Operator);
    $this->bag->update(['minimum_stock' => 100]);

    $this->post(route('production.shrink-wraps.store'), shrinkWrapPayload())->assertSessionHasNoErrors();

    expect(Alert::query()->where('type', AlertType::StockBajo)->where('raw_material_id', $this->bag->id)->where('is_resolved', false)->exists())
        ->toBeTrue();
});

it('valida el registro', function (Closure $overrides, string $field) {
    actingAsRole(SystemRole::Operator);

    $this->post(route('production.shrink-wraps.store'), shrinkWrapPayload($overrides()))->assertSessionHasErrors($field);

    expect(ShrinkWrap::query()->exists())->toBeFalse()
        ->and($this->oldBags->fresh()->remaining_quantity)->toBe('5.0000');
})->with([
    'OP no completada' => [fn () => ['production_order_id' => ProductionOrder::factory()->create(['status' => ProductionOrderStatus::InProgress])->id], 'production_order_id'],
    'tipo inactivo' => [fn () => ['shrink_wrap_type_id' => ShrinkWrapType::factory()->inactive()->create()->id], 'shrink_wrap_type_id'],
    'sin aplicaciones' => [fn () => ['applications' => ''], 'applications'],
    'cero aplicaciones' => [fn () => ['applications' => 0], 'applications'],
    'aplicaciones con decimales' => [fn () => ['applications' => '1.5'], 'applications'],
    'aplicaciones en notación científica' => [fn () => ['applications' => '1e3'], 'applications'],
    'fecha futura' => [fn () => ['wrapped_at' => '2026-10-06'], 'wrapped_at'],
    'fecha anterior a completar la OP' => [fn () => ['wrapped_at' => '2026-09-30'], 'wrapped_at'],
    'fecha con otro formato' => [fn () => ['wrapped_at' => '05/10/2026'], 'wrapped_at'],
    'notas muy largas' => [fn () => ['notes' => str_repeat('a', 1001)], 'notes'],
]);

it('rechaza unas aplicaciones cuyo consumo no cabe en la columna, sin llegar a la base', function () {
    actingAsRole(SystemRole::Operator);
    ShrinkWrapTypeItem::query()->where('raw_material_id', $this->bag->id)->update(['quantity' => '50']);

    // 50 × 9.999.999 = 499.999.950, más que el tope de decimal(12,4).
    $this->post(route('production.shrink-wraps.store'), shrinkWrapPayload(['applications' => 9999999]))
        ->assertSessionHasErrors(['applications' => 'Son demasiadas aplicaciones: el consumo de BOLSA-1 no cabe en el registro.']);
});

it('toma la fecha de la planta: de noche en Bogotá, «mañana» UTC sigue siendo futuro', function () {
    actingAsRole(SystemRole::Operator);
    // 01:30 UTC del 6 = 20:30 del 5 en Bogotá.
    $this->travelTo(CarbonImmutable::parse('2026-10-06 01:30:00', 'UTC'));

    $this->post(route('production.shrink-wraps.store'), shrinkWrapPayload(['wrapped_at' => '2026-10-06']))
        ->assertSessionHasErrors('wrapped_at');

    $this->post(route('production.shrink-wraps.store'), shrinkWrapPayload(['wrapped_at' => '2026-10-05']))
        ->assertSessionHasNoErrors();
});

it('deja registrar a Producción y Operario, y ver a Admin', function () {
    actingAsRole(SystemRole::Production);
    $this->get(route('production.shrink-wraps.create'))->assertSuccessful();
    $this->post(route('production.shrink-wraps.store'), shrinkWrapPayload())->assertSessionHasNoErrors();

    actingAsRole(SystemRole::Admin);
    $this->get(route('production.shrink-wraps.index'))->assertSuccessful();
    $this->get(route('production.shrink-wraps.show', ShrinkWrap::query()->sole()))->assertSuccessful();
});

it('niega el termoencogido a Comercial', function () {
    actingAsRole(SystemRole::Commercial);
    $shrinkWrap = ShrinkWrap::factory()->create(['production_order_id' => $this->order->id]);

    $this->get(route('production.shrink-wraps.index'))->assertForbidden();
    $this->get(route('production.shrink-wraps.show', $shrinkWrap))->assertForbidden();
    $this->get(route('production.shrink-wraps.create'))->assertForbidden();
    $this->post(route('production.shrink-wraps.store'), shrinkWrapPayload())->assertForbidden();
});

it('muestra el costo solo a quien tiene costs.view', function () {
    actingAsRole(SystemRole::Production);
    $this->post(route('production.shrink-wraps.store'), shrinkWrapPayload());
    $shrinkWrap = ShrinkWrap::query()->sole();

    $this->get(route('production.shrink-wraps.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Production/ShrinkWraps/Index')
            ->where('can.viewCosts', false)
            ->where('shrinkWraps.data.0.total_cost', null));
    $this->get(route('production.shrink-wraps.show', $shrinkWrap))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('shrinkWrap.total_cost', null)
            ->where('shrinkWrap.items.0.total_cost', null));

    actingAsRole(SystemRole::Admin);
    $this->get(route('production.shrink-wraps.show', $shrinkWrap))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('shrinkWrap.total_cost', '1500.0000')
            ->where('shrinkWrap.registered_on', '2026-10-05')
            ->where('shrinkWrap.order.order_number', 'OP-2026-0042')
            ->where('shrinkWrap.type_name', 'Galón')
            ->has('shrinkWrap.items', 2));
});

it('ofrece solo OP completadas y tipos activos con su receta', function () {
    actingAsRole(SystemRole::Operator);
    ProductionOrder::factory()->create(['status' => ProductionOrderStatus::InProgress, 'order_number' => 'OP-2026-0099']);
    ShrinkWrapType::factory()->inactive()->create(['name' => 'Viejo']);

    $this->get(route('production.shrink-wraps.create'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Production/ShrinkWraps/Create')
            ->where('today', '2026-10-05')
            ->where('orderOptions', fn ($orders) => collect($orders)->pluck('order_number')->all() === ['OP-2026-0042'])
            ->where('orderOptions.0.completion_date', '2026-10-01')
            ->where('typeOptions', fn ($types) => collect($types)->pluck('label')->all() === ['Galón'])
            ->has('typeOptions.0.items', 2));
});

it('busca por número de OP y filtra por tipo', function () {
    actingAsRole(SystemRole::Operator);
    $this->post(route('production.shrink-wraps.store'), shrinkWrapPayload());
    $other = ShrinkWrap::factory()->create();

    $this->get(route('production.shrink-wraps.index', ['search' => '0042']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('shrinkWraps.data', fn ($rows) => collect($rows)->pluck('order.order_number')->all() === ['OP-2026-0042']));

    $this->get(route('production.shrink-wraps.index', ['shrink_wrap_type_id' => $other->shrink_wrap_type_id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('shrinkWraps.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$other->id]));
});

it('no deja eliminar un tipo que ya tiene registros, y su receta sigue intacta', function () {
    actingAsRole(SystemRole::Admin);
    $this->post(route('production.shrink-wraps.store'), shrinkWrapPayload())->assertSessionHasNoErrors();

    $this->get(route('catalogs.shrink-wrap-types.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('types.data.0.in_use', true));

    $this->delete(route('catalogs.shrink-wrap-types.destroy', $this->type))
        ->assertSessionHas('error', 'El tipo de termoencogido ya se usó. Desactívalo en su lugar.');

    expect($this->type->fresh())->not->toBeNull()
        ->and($this->type->items()->count())->toBe(2);
});
