<?php

declare(strict_types=1);

/*
 * Auditoría 3.6, PR 3: las fechas que digita el usuario se validan contra el día de la planta (TimezoneService), con
 * formato Y-m-d estricto y mensajes propios. El reloj queda a las 20:30 del 13-oct en Bogotá, cuando en UTC ya es 14-oct:
 * es la franja en que «hoy» en UTC rechazaba el día de la planta (COT-01, PV-01).
 */

use App\Enums\FinishedInventoryMovementReason;
use App\Enums\PaintDevelopmentRequestStatus;
use App\Enums\ProductionOrderStatus;
use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Enums\SystemRole;
use App\Models\Client;
use App\Models\FinishedProductBatch;
use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\InventoryBatch;
use App\Models\PaintDevelopmentRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\ProductVariant;
use App\Models\Quotation;
use App\Models\RawMaterial;
use App\Models\SalesOrder;
use App\Models\ShrinkWrapType;
use App\Models\ShrinkWrapTypeItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\FinishedInventory\FinishedInventoryMovementService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-14 01:30:00', 'UTC'));
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(SystemRole::Admin->value);

    $unit = UnitOfMeasure::factory()->create();
    $this->unit = $unit;
    $this->product = Product::create([
        'code' => 'FECHA-1', 'name' => 'Esmalte fecha', 'brand' => 'BEPRO',
        'category_id' => ProductCategory::create(['name' => 'Categoría fechas'])->id,
        'unit_of_measure_id' => $unit->id, 'current_cost' => 10, 'current_price' => 1000, 'sales_margin' => 25,
        'cif_percentage' => 15, 'price_threshold' => 3, 'is_active' => true,
    ]);
    $this->variant = ProductVariant::create([
        'product_id' => $this->product->id, 'code' => 'V-FECHA-1', 'name' => 'GAL', 'unit_of_measure_id' => $unit->id,
        'presentation_value' => 1, 'presentation_label' => 'GAL', 'current_price' => 1000, 'is_active' => true,
    ]);
    $this->client = Client::factory()->create();
});

describe('pedidos de venta: fecha requerida al crear (PV-01)', function (): void {
    beforeEach(function (): void {
        $this->send = fn (string $date) => $this->actingAs($this->admin)->post(route('sales-orders.store'), [
            'client_id' => $this->client->id,
            'priority' => 'medium',
            'required_date' => $date,
            'items' => [['product_id' => $this->product->id, 'product_variant_id' => $this->variant->id, 'quantity' => '3']],
        ]);
    });

    it('accepts today in the plant after 7 p. m.', function (): void {
        ($this->send)('2026-10-13')->assertSessionHasNoErrors();

        expect(SalesOrder::query()->sole()->getRawOriginal('required_date'))->toStartWith('2026-10-13');
    });

    it('rejects yesterday and a non Y-m-d date', function (string $date, string $message): void {
        ($this->send)($date)->assertSessionHasErrors(['required_date' => $message]);
    })->with([
        'ayer' => ['2026-10-12', 'La fecha requerida no puede ser anterior a hoy.'],
        'formato de EE. UU.' => ['12/31/2026', 'Elige una fecha válida.'],
    ]);
});

describe('pedidos de venta: fecha estimada de entrega (PV-05, D-PV3)', function (): void {
    beforeEach(function (): void {
        // Creado a las 20:30 del 13-oct en Bogotá: su día de creación es 13-oct, aunque created_at ya diga 14-oct en UTC.
        $this->order = SalesOrder::factory()->create([
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'status' => SalesOrderStatus::Pending,
        ]);
        $this->send = fn (?string $date) => $this->actingAs($this->admin)
            ->patch(route('sales-orders.update', $this->order), ['estimated_delivery_date' => $date]);
    });

    it('accepts the day the order was created, a later day or no date', function (?string $date): void {
        ($this->send)($date)->assertSessionHasNoErrors();

        expect($this->order->refresh()->getRawOriginal('estimated_delivery_date'))->toBe($date);
    })->with(['2026-10-13', '2026-11-30', null]);

    it('rejects a day before the order was created and a non Y-m-d date', function (string $date, string $message): void {
        ($this->send)($date)->assertSessionHasErrors(['estimated_delivery_date' => $message]);
    })->with([
        'antes de crear el pedido' => ['2026-10-12', 'La fecha estimada de entrega no puede ser anterior a la fecha del pedido (13/10/2026).'],
        'año 1' => ['0001-01-01', 'La fecha estimada de entrega no puede ser anterior a la fecha del pedido (13/10/2026).'],
        'formato de EE. UU.' => ['12/31/2026', 'Elige una fecha válida.'],
    ]);

    // Una fecha estimada vieja (de antes de la regla) no debe dejar el pedido sin poder guardarse: el formulario la
    // reenvía aunque solo cambien las notas.
    it('keeps an old estimated date before the creation day, but only that one', function (string $date, ?string $message): void {
        $this->order->update(['estimated_delivery_date' => '2026-10-10']);

        $response = $this->actingAs($this->admin)->patch(route('sales-orders.update', $this->order), [
            'estimated_delivery_date' => $date,
            'notes' => 'Cambio de nota',
        ]);

        $message === null
            ? $response->assertSessionHasNoErrors()
            : $response->assertSessionHasErrors(['estimated_delivery_date' => $message]);
    })->with([
        'la fecha guardada, sin cambiarla' => ['2026-10-10', null],
        'otra fecha anterior a la creación' => ['2026-10-11', 'La fecha estimada de entrega no puede ser anterior a la fecha del pedido (13/10/2026).'],
    ]);
});

describe('cotizaciones: fecha requerida al convertir en pedido (COT-01)', function (): void {
    beforeEach(function (): void {
        $this->quotation = Quotation::factory()->create([
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'status' => QuotationStatus::Accepted,
        ]);
        $this->quotation->items()->create([
            'product_id' => $this->product->id, 'product_variant_id' => $this->variant->id, 'type' => 'primer',
            'description' => 'Esmalte', 'quantity' => 3, 'list_unit_price' => 1000, 'price_adjustment_pct' => 0,
            'unit_price' => 1000, 'subtotal' => 3000, 'sort_order' => 1,
        ]);
        $this->send = fn (string $date) => $this->actingAs($this->admin)
            ->post(route('quotations.convert-to-order', $this->quotation), ['priority' => 'medium', 'required_date' => $date]);
    });

    it('accepts today in the plant after 7 p. m.', function (): void {
        ($this->send)('2026-10-13')->assertSessionHasNoErrors();

        expect(SalesOrder::query()->sole()->getRawOriginal('required_date'))->toStartWith('2026-10-13');
    });

    it('rejects yesterday and a non Y-m-d date, in Spanish', function (string $date, string $message): void {
        ($this->send)($date)->assertSessionHasErrors(['required_date' => $message]);
    })->with([
        'ayer' => ['2026-10-12', 'La fecha requerida no puede ser anterior a hoy.'],
        'formato de EE. UU.' => ['09/10/2026', 'Elige una fecha válida.'],
    ]);
});

describe('cotizaciones: fecha de cotización (COT-06, D-COT2)', function (): void {
    beforeEach(function (): void {
        $this->payload = fn (string $date) => [
            'client_id' => $this->client->id,
            'quotation_date' => $date,
            'validity_days' => 30,
            'payment_method' => 'cash',
            'iva_percentage' => '19',
            'items' => [[
                'product_id' => $this->product->id, 'product_variant_id' => $this->variant->id, 'type' => 'primer',
                'description' => 'Esmalte', 'quantity' => '3', 'price_adjustment_pct' => '0',
            ]],
        ];
        $this->send = fn (string $date) => $this->actingAs($this->admin)
            ->post(route('quotations.store'), ($this->payload)($date));
    });

    it('accepts today in the plant and a past date', function (string $date): void {
        ($this->send)($date)->assertSessionHasNoErrors();

        expect(Quotation::query()->sole()->getRawOriginal('quotation_date'))->toStartWith($date);
    })->with(['2026-10-13', '2026-01-15']);

    it('rejects a future date and a non Y-m-d date', function (string $date, string $message): void {
        ($this->send)($date)->assertSessionHasErrors(['quotation_date' => $message]);
    })->with([
        'hoy en UTC (mañana en planta)' => ['2026-10-14', 'La fecha de cotización no puede ser futura.'],
        'formato de EE. UU.' => ['09/10/2026', 'Elige una fecha válida.'],
    ]);

    // Un borrador viejo con fecha futura (de antes de la regla) se puede volver a guardar sin cambiarla.
    it('keeps the saved future date of an old draft, but only that one', function (string $date, ?string $message): void {
        $draft = Quotation::factory()->create([
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'status' => QuotationStatus::Draft,
            'quotation_date' => '2026-10-20',
        ]);

        $response = $this->actingAs($this->admin)->put(route('quotations.update', $draft), ($this->payload)($date));

        $message === null
            ? $response->assertSessionHasNoErrors()
            : $response->assertSessionHasErrors(['quotation_date' => $message]);
    })->with([
        'la fecha guardada, sin cambiarla' => ['2026-10-20', null],
        'otra fecha futura' => ['2026-10-21', 'La fecha de cotización no puede ser futura.'],
        'hoy en planta' => ['2026-10-13', null],
    ]);
});

describe('movimientos de materia prima (INV-03, D1)', function (): void {
    beforeEach(function (): void {
        $this->warehouse = Warehouse::factory()->factory()->create();
        $this->material = RawMaterial::factory()->create();
        $this->batch = InventoryBatch::factory()->create([
            'raw_material_id' => $this->material->id, 'warehouse_id' => $this->warehouse->id,
            'initial_quantity' => '10.0000', 'remaining_quantity' => '10.0000', 'unit_price' => '1000.0000',
            'entry_date' => '2026-01-01',
        ]);
        $this->entry = fn (array $o) => $this->actingAs($this->admin)->post(route('inventory-movements.store'), array_merge([
            'raw_material_id' => $this->material->id, 'warehouse_id' => $this->warehouse->id, 'batch_id' => null,
            'type' => 'entry', 'quantity' => '10', 'cost_price' => '1000', 'movement_date' => '2026-10-13',
            'lot_number' => 'L-FECHA', 'supplier' => 'Proveedor', 'expiry_date' => null, 'notes' => null,
        ], $o));
        $this->exit = fn (array $o) => $this->actingAs($this->admin)->post(route('inventory-movements.store'), array_merge([
            'raw_material_id' => $this->material->id, 'warehouse_id' => $this->warehouse->id, 'batch_id' => $this->batch->id,
            'type' => 'exit', 'quantity' => '1', 'movement_date' => '2026-10-13', 'notes' => null,
        ], $o));
    });

    it('accepts today in the plant after 7 p. m. and a far expiry date', function (): void {
        ($this->entry)(['expiry_date' => '2099-12-31'])->assertSessionHasNoErrors();
        ($this->exit)([])->assertSessionHasNoErrors();
    });

    it('rejects a future or non Y-m-d movement date', function (string $type, string $date, string $message): void {
        ($this->{$type})(['movement_date' => $date])->assertSessionHasErrors(['movement_date' => $message]);
    })->with([
        'entrada con hoy en UTC' => ['entry', '2026-10-14', 'La fecha de movimiento no puede ser futura.'],
        'salida con hoy en UTC' => ['exit', '2026-10-14', 'La fecha de movimiento no puede ser futura.'],
        'entrada en formato de EE. UU.' => ['entry', '09/10/2026', 'Elige una fecha válida.'],
        'entrada en el año 9999' => ['entry', '9999-12-31', 'La fecha de movimiento no puede ser futura.'],
    ]);

    it('rejects an expiry date that does not exist', function (): void {
        ($this->entry)(['expiry_date' => '2027-02-30'])->assertSessionHasErrors(['expiry_date' => 'Elige una fecha válida.']);
    });
});

describe('movimientos de producto terminado (PT-05, D-PT2)', function (): void {
    beforeEach(function (): void {
        $this->warehouse = Warehouse::factory()->factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $this->product->id]);
        $this->batch = FinishedProductBatch::create([
            'product_id' => $this->product->id, 'product_variant_id' => $variant->id,
            'production_order_id' => ProductionOrder::factory()->create(['product_id' => $this->product->id])->id,
            'initial_quantity' => '20', 'entry_date' => '2026-10-01',
        ]);
        app(FinishedInventoryMovementService::class)->registerEntry(
            batchId: $this->batch->id, warehouseId: $this->warehouse->id, quantity: '20',
            reason: FinishedInventoryMovementReason::Production, userId: $this->admin->id, movementDate: '2026-10-01',
        );
        $this->send = fn (string $date) => $this->actingAs($this->admin)->post(route('finished-inventory-movements.store'), [
            'finished_product_batch_id' => $this->batch->id, 'warehouse_id' => $this->warehouse->id, 'type' => 'exit',
            'reason' => FinishedInventoryMovementReason::Sale->value, 'quantity' => '2', 'movement_date' => $date,
            'destination_warehouse_id' => null, 'notes' => null,
        ]);
    });

    it('accepts today in the plant after 7 p. m.', function (): void {
        ($this->send)('2026-10-13')->assertSessionHasNoErrors();
    });

    it('rejects a future or non Y-m-d movement date', function (string $date, string $message): void {
        ($this->send)($date)->assertSessionHasErrors(['movement_date' => $message]);
    })->with([
        'hoy en UTC' => ['2026-10-14', 'La fecha de movimiento no puede ser futura.'],
        'formato de EE. UU.' => ['09/10/2026', 'Elige una fecha válida.'],
    ]);
});

describe('orden de producción: fecha planificada (OPC-05, D-OP2)', function (): void {
    beforeEach(function (): void {
        $warehouse = Warehouse::factory()->factory()->create();
        $kg = UnitOfMeasure::create(['code' => 'KGF', 'name' => 'Kilogramo fechas', 'symbol' => 'kgf', 'to_kg_conversion' => 1]);
        $material = RawMaterial::factory()->create(['unit_of_measure_id' => $kg->id]);
        InventoryBatch::factory()->create([
            'raw_material_id' => $material->id, 'warehouse_id' => $warehouse->id,
            'initial_quantity' => 1000, 'remaining_quantity' => 1000, 'unit_price' => 100,
        ]);
        $formula = Formula::create(['product_id' => $this->product->id, 'version' => 1, 'is_active' => true, 'created_by' => $this->admin->id]);
        FormulaDetail::create(['formula_id' => $formula->id, 'raw_material_id' => $material->id, 'quantity' => '1', 'unit_of_measure_id' => $kg->id]);
        $this->send = fn (string $date) => $this->actingAs($this->admin)->post(route('production-orders.store'), [
            'product_id' => $this->product->id, 'formula_id' => $formula->id, 'warehouse_id' => $warehouse->id,
            'quantity' => '10', 'planned_date' => $date, 'notes' => null, 'color' => null,
            'packaging' => [['product_variant_id' => $this->variant->id, 'planned_units' => '10']],
        ]);
    });

    it('accepts today in the plant after 7 p. m.', function (): void {
        ($this->send)('2026-10-13')->assertSessionHasNoErrors();

        expect(ProductionOrder::query()->sole()->getRawOriginal('planned_date'))->toStartWith('2026-10-13');
    });

    it('rejects a past or non Y-m-d planned date', function (string $date, string $message): void {
        ($this->send)($date)->assertSessionHasErrors(['planned_date' => $message]);
    })->with([
        'ayer' => ['2026-10-12', 'La fecha planificada no puede ser anterior a hoy.'],
        'formato de EE. UU.' => ['09/10/2026', 'Elige una fecha válida.'],
    ]);
});

describe('solicitudes de I+D: fecha de entrega de muestra (PDR-01, D-PDR1)', function (): void {
    beforeEach(function (): void {
        $this->payload = fn (string $date) => [
            'client_name' => 'Cliente Libre S.A.S.', 'project_name' => 'Proyecto fechas', 'responsible' => 'Juan Pérez',
            'city' => 'Bogotá', 'sample_due_date' => $date, 'current_product' => null,
            'context_payload' => [
                'sustrato' => 'Acero al carbono', 'estado_superficie' => 'Nueva / sin pintar', 'pintura_existente' => 'No',
                'preparacion' => ['Limpieza / desengrase'], 'exposicion' => ['Interior'], 'agua_humedad' => 'Humedad ambiental',
                'ciclos_termicos' => 'No',
            ],
            'performance_payload' => [
                'funcion' => ['Anticorrosiva'], 'vida_util' => '5–10 años', 'prioridad' => 'Máxima protección',
                'color' => 'RAL 7040', 'brillo' => 'Semimate', 'textura' => 'Lisa', 'cubricion' => 'Dos manos',
                'retencion' => 'Alta', 'indispensable' => 'Adherencia',
            ],
            'application_payload' => [
                'metodo' => ['Airless'], 'geometria' => 'Estructuras grandes', 'manos' => '2', 'repinte' => '24 horas',
                'servicio' => '72 horas', 'antidescuelgue' => 'Normal', 'vehiculo' => 'Base agua', 'tecnologia' => 'Epóxica',
                'componentes' => '2K — Dos componentes', 'ajustador' => 'No', 'sistema_capas' => 'Imprimante + acabado',
            ],
            'specifications_payload' => [
                'secados' => 'Tacto 2h', 'estabilidad' => '12 meses', 'presentacion' => ['Galón'], 'consumo' => '300 galones/mes',
                'frecuencia' => 'Mensual', 'meta_competidor' => 'Igualar', 'costo_objetivo' => '$28.000 COP/kg',
                'cantidad_prueba' => '5 galones', 'aprobador' => 'Carlos Ruiz', 'forma_aprobacion' => 'Prueba en campo',
                'criterios_aprobacion' => 'Aprobación del cliente',
            ],
        ];
        $this->create = fn (string $date) => $this->actingAs($this->admin)
            ->post(route('paint-development-requests.store'), ($this->payload)($date));
    });

    it('accepts today in the plant after 7 p. m. when creating', function (): void {
        ($this->create)('2026-10-13')->assertSessionHasNoErrors();

        expect(PaintDevelopmentRequest::query()->sole()->getRawOriginal('sample_due_date'))->toStartWith('2026-10-13');
    });

    it('rejects a past or non Y-m-d date when creating', function (string $date, string $message): void {
        ($this->create)($date)->assertSessionHasErrors(['sample_due_date' => $message]);
    })->with([
        'ayer' => ['2026-10-12', 'La fecha de entrega de muestra no puede ser anterior a hoy.'],
        'formato de EE. UU.' => ['09/10/2026', 'Elige una fecha válida.'],
    ]);

    it('keeps the saved past date of an old draft, but only that one', function (string $date, ?string $message): void {
        $draft = PaintDevelopmentRequest::factory()->create([
            'status' => PaintDevelopmentRequestStatus::Draft,
            'created_by' => $this->admin->id,
            'sample_due_date' => '2026-09-01',
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('paint-development-requests.update', $draft), ($this->payload)($date));

        $message === null
            ? $response->assertSessionHasNoErrors()
            : $response->assertSessionHasErrors(['sample_due_date' => $message]);
    })->with([
        'la fecha guardada, sin cambiarla' => ['2026-09-01', null],
        'otra fecha pasada' => ['2026-09-02', 'La fecha de entrega de muestra no puede ser anterior a hoy.'],
        'hoy en planta' => ['2026-10-13', null],
        'una fecha futura' => ['2026-11-15', null],
    ]);
});

describe('termoencogido: fecha (SW-01)', function (): void {
    beforeEach(function (): void {
        $warehouse = Warehouse::factory()->factory()->create();
        $order = ProductionOrder::factory()->create([
            'warehouse_id' => $warehouse->id, 'status' => ProductionOrderStatus::Completed, 'completion_date' => '2026-10-01',
        ]);
        $bag = RawMaterial::factory()->secondaryPackaging()->create(['minimum_stock' => 0]);
        InventoryBatch::factory()->create([
            'raw_material_id' => $bag->id, 'warehouse_id' => $warehouse->id, 'initial_quantity' => 100,
            'remaining_quantity' => 100, 'unit_price' => 100, 'entry_date' => '2026-09-01',
        ]);
        $type = ShrinkWrapType::factory()->create();
        ShrinkWrapTypeItem::factory()->create(['shrink_wrap_type_id' => $type->id, 'raw_material_id' => $bag->id, 'quantity' => '1']);
        $this->send = fn (string $date) => $this->actingAs($this->admin)->post(route('production.shrink-wraps.store'), [
            'production_order_id' => $order->id, 'shrink_wrap_type_id' => $type->id, 'applications' => '8',
            'wrapped_at' => $date, 'notes' => null,
        ]);
    });

    it('accepts today in the plant after 7 p. m.', function (): void {
        ($this->send)('2026-10-13')->assertSessionHasNoErrors();
    });

    it('rejects a future or non Y-m-d date', function (string $date, string $message): void {
        ($this->send)($date)->assertSessionHasErrors(['wrapped_at' => $message]);
    })->with([
        'hoy en UTC' => ['2026-10-14', 'La fecha no puede ser futura.'],
        'formato de EE. UU.' => ['09/10/2026', 'Elige una fecha válida.'],
        'día que no existe' => ['2026-02-30', 'Elige una fecha válida.'],
    ]);
});
