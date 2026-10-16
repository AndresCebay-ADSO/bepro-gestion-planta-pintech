<?php

declare(strict_types=1);

/*
 * Auditoría 3.6, PR 3: los valores por defecto y los límites que el servidor le da al front salen del día de la planta.
 * Reloj a las 20:30 del 13-oct en Bogotá (14-oct en UTC).
 */

use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Enums\SystemRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-14 01:30:00', 'UTC'));
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(SystemRole::Admin->value);
    $this->client = Client::factory()->create();
});

describe('fecha de cotización por defecto (COT-06, D-COT2)', function (): void {
    beforeEach(function (): void {
        $unit = UnitOfMeasure::factory()->create();
        $product = Product::create([
            'name' => 'Esmalte por defecto',
            'category_id' => ProductCategory::create(['name' => 'Categoría por defecto'])->id,
            'unit_of_measure_id' => $unit->id, 'current_price' => 1000, 'sales_margin' => 25, 'cif_percentage' => 15,
            'price_threshold' => 3, 'is_active' => true,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'code' => 'V-COT-DEF', 'name' => 'GAL', 'unit_of_measure_id' => $unit->id,
            'presentation_value' => 1, 'presentation_label' => 'GAL', 'current_price' => 1000, 'is_active' => true,
        ]);
        $this->payload = fn (array $o = []) => array_merge([
            'client_id' => $this->client->id,
            'validity_days' => 30,
            'payment_method' => 'cash',
            'iva_percentage' => '19',
            'items' => [[
                'product_id' => $product->id, 'product_variant_id' => $variant->id, 'type' => 'primer',
                'description' => 'Esmalte', 'quantity' => '3', 'price_adjustment_pct' => '0',
            ]],
        ], $o);
    });

    it('uses today in the plant when a new quotation comes without a date', function (): void {
        $this->actingAs($this->admin)
            ->post(route('quotations.store'), ($this->payload)(['quotation_date' => null]))
            ->assertSessionHasNoErrors();

        expect(Quotation::query()->sole()->getRawOriginal('quotation_date'))->toStartWith('2026-10-13');
    });

    it('keeps the saved date when an edited draft comes without one', function (): void {
        $draft = Quotation::factory()->create([
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'status' => QuotationStatus::Draft,
            'quotation_date' => '2026-01-15',
        ]);

        $this->actingAs($this->admin)
            ->put(route('quotations.update', $draft), ($this->payload)(['quotation_date' => null]))
            ->assertSessionHasNoErrors();

        expect($draft->refresh()->getRawOriginal('quotation_date'))->toStartWith('2026-01-15');
    });
});

// El cajón de movimientos se abre con una recarga parcial, que no trae los props compartidos salvo los `always`: sin
// eso, una página cargada ayer propondría la fecha de ayer al abrir el cajón hoy.
it('refreshes today in the plant on the partial reload that opens a movement drawer', function (): void {
    $response = $this->actingAs($this->admin)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'Inventory/Movements/Index',
            'X-Inertia-Partial-Data' => 'rawMaterials,oldestEntryDate',
        ])
        ->get(route('inventory-movements.index'));

    $response->assertOk();
    expect($response->json('props.plantToday'))->toBe('2026-10-13')
        ->and($response->json('props.oldestEntryDate'))->toBe('2026-09-13');
});

it('shares today in the plant with every page', function (): void {
    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('plantToday', '2026-10-13'));
});

it('gives the sales order page the plant day the order was created (D-PV3)', function (): void {
    $order = SalesOrder::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->admin->id,
        'status' => SalesOrderStatus::Pending,
    ]);

    $this->actingAs($this->admin)
        ->get(route('sales-orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page->where('order.created_on', '2026-10-13'));
});
