<?php

declare(strict_types=1);

use App\Actions\Quotations\BuildQuotationPdfDataAction;
use App\Enums\QuotationStatus;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\QuotationService;
use App\Services\VariantSalesPriceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->unit = UnitOfMeasure::factory()->create();

    $this->category = ProductCategory::create([
        'name' => 'Categoría Test',
    ]);

    $this->product = Product::create([
        'code' => 'PROD-COT',
        'name' => 'Esmalte Dualtech',
        'brand' => 'BEPRO',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'current_price' => 67192.0000,
        'sales_margin' => 25.00,
        'cif_percentage' => 15,
        'price_threshold' => 3,
        'is_active' => true,
    ]);

    $this->variant = ProductVariant::create([
        'product_id' => $this->product->id,
        'code' => 'VAR-COT-001',
        'name' => 'GRIS RAL 7001 GAL',
        'unit_of_measure_id' => $this->unit->id,
        'presentation_value' => 1,
        'presentation_label' => 'GAL',
        'current_price' => 67192.0000,
        'is_active' => true,
    ]);

    $this->comercialUser = User::factory()->create(['email_verified_at' => now()]);
    $this->comercialUser->assignRole('comercial');

    $this->otherComercial = User::factory()->create(['email_verified_at' => now()]);
    $this->otherComercial->assignRole('comercial');

    $this->client = Client::factory()->create();
});

function quotationPayload(int $clientId, int $productId, int $variantId, array $overrides = []): array
{
    return array_merge([
        'client_id' => $clientId,
        'client_business_name' => 'PSI CONSTRUCCIONES',
        'client_nit' => '901674872',
        'client_contact_name' => 'SEBASTIAN OSORIO',
        'client_phone' => '311-3068435',
        'technology' => 'Alquídico',
        'line' => 'Core Series',
        'quotation_date' => now()->toDateString(),
        'validity_days' => 30,
        'payment_method' => 'cash',
        'delivery_time' => '2 Días Hab.',
        'iva_percentage' => 19,
        'items' => [
            [
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'type' => 'primer',
                'description' => 'Esmalte Industrial 2 en 1',
                'quantity' => 3,
                'price_adjustment_pct' => 0,
            ],
        ],
    ], $overrides);
}

it('allows comercial to create a quotation using sales list price', function () {
    $salesPriceService = app(VariantSalesPriceService::class);
    $expectedListPrice = (float) $salesPriceService->resolveForVariant($this->variant);

    $this->actingAs($this->comercialUser)
        ->post(route('quotations.store'), quotationPayload(
            $this->client->id,
            $this->product->id,
            $this->variant->id,
        ))
        ->assertRedirect();

    $quotation = Quotation::query()->latest()->first();

    expect($quotation)->not->toBeNull();
    expect($quotation->quotation_number)->toBe(1);
    expect($quotation->created_by)->toBe($this->comercialUser->id);
    expect($quotation->items)->toHaveCount(1);

    $item = $quotation->items->first();
    expect((float) $item->list_unit_price)->toEqual($expectedListPrice);
    expect((float) $item->unit_price)->toEqual($expectedListPrice);
    expect((float) $item->subtotal)->toEqual(round($expectedListPrice * 3, 4));
});

it('applies negative price adjustment percentage on quotation items', function () {
    $this->actingAs($this->comercialUser)
        ->post(route('quotations.store'), quotationPayload(
            $this->client->id,
            $this->product->id,
            $this->variant->id,
            [
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'product_variant_id' => $this->variant->id,
                        'quantity' => 1,
                        'price_adjustment_pct' => -10,
                    ],
                ],
            ],
        ))
        ->assertRedirect();

    $item = Quotation::query()->latest()->first()?->items->first();
    $listPrice = (float) $item->list_unit_price;

    expect((float) $item->price_adjustment_pct)->toEqual(-10.0);
    expect((float) $item->unit_price)->toEqual(round($listPrice / 1.1, 4));
});

it('prevents comercial from viewing another users quotation', function () {
    $quotation = Quotation::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->comercialUser->id,
    ]);

    $this->actingAs($this->otherComercial)
        ->get(route('quotations.show', $quotation))
        ->assertForbidden();
});

it('renders quotation index for comercial user', function () {
    Quotation::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->comercialUser->id,
    ]);

    $this->actingAs($this->comercialUser)
        ->get(route('quotations.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Quotations/Index')
            ->has('quotations.data', 1)
            ->where('can.create', true)
        );
});

it('exports quotation pdf', function () {
    $quotation = Quotation::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->comercialUser->id,
        'quotation_number' => 1,
        'subtotal' => 300770,
        'iva_amount' => 57146.3,
        'total' => 357916.3,
    ]);

    $quotation->items()->create([
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'type' => 'primer',
        'description' => 'Esmalte Industrial 2 en 1',
        'quantity' => 3,
        'list_unit_price' => 83940,
        'price_adjustment_pct' => 0,
        'unit_price' => 83940,
        'subtotal' => 251820,
        'sort_order' => 1,
    ]);

    $this->actingAs($this->comercialUser)
        ->get(route('quotations.export-pdf', $quotation))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('renders quotation pdf template with bepro layout sections', function () {
    $this->comercialUser->update(['phone' => '3009876543']);

    $quotation = Quotation::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->comercialUser->id,
        'quotation_number' => 4415,
        'technology' => 'Epoxy',
        'line' => 'Pro Series',
        'area' => '600',
        'subtotal' => 6580000,
        'iva_amount' => 1250200,
        'total' => 7830200,
    ]);

    $quotation->items()->create([
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'type' => 'self_priming',
        'description' => 'Epoxy autoimprimante',
        'color' => 'GRIS',
        'quantity' => 1,
        'list_unit_price' => 6580000,
        'price_adjustment_pct' => 0,
        'unit_price' => 6580000,
        'subtotal' => 6580000,
        'sort_order' => 1,
    ]);

    $quotationData = app(BuildQuotationPdfDataAction::class)->execute($quotation);

    $html = view('pdf.quotation', [
        'quotation' => $quotationData,
        'beproLogoBase64' => null,
        'pintechLogoBase64' => null,
        'generatedAt' => now()->format('d/m/Y H:i'),
    ])->render();

    expect($html)
        ->toContain('Cotización No.4415')
        ->toContain('www.beprocoatings.com')
        ->toContain('Sistema ofertado')
        ->toContain('Condiciones comerciales')
        ->toContain('Producto / Referencia')
        ->toContain('SEDE CALI')
        ->toContain('SEDE NEIVA')
        ->toContain('Notas y alcance')
        ->toContain('Móvil: 3009876543');
});

it('uses shared sales price service for list prices', function () {
    $service = app(VariantSalesPriceService::class);

    expect((float) $service->resolveForVariant($this->variant))->toEqual(89589.3333);
});

it('validates quotation update requires draft status', function () {
    $quotation = Quotation::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->comercialUser->id,
        'status' => QuotationStatus::Sent,
    ]);

    $this->actingAs($this->comercialUser)
        ->put(route('quotations.update', $quotation), quotationPayload(
            $this->client->id,
            $this->product->id,
            $this->variant->id
        ))
        ->assertForbidden();
});

it('allows quotation update in draft status', function () {
    $quotation = Quotation::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->comercialUser->id,
        'status' => QuotationStatus::Draft,
    ]);

    $this->actingAs($this->comercialUser)
        ->put(route('quotations.update', $quotation), quotationPayload(
            $this->client->id,
            $this->product->id,
            $this->variant->id,
            ['notes' => 'Updated notes']
        ))
        ->assertRedirect();

    expect($quotation->fresh()->notes)->toBe('Updated notes');
});

it('fails validation when items array is empty', function () {
    $this->actingAs($this->comercialUser)
        ->post(route('quotations.store'), quotationPayload(
            $this->client->id,
            $this->product->id,
            $this->variant->id,
            ['items' => []]
        ))
        ->assertInvalid(['items']);
});

it('fails validation when product is soft deleted', function () {
    $this->product->delete();

    $this->actingAs($this->comercialUser)
        ->post(route('quotations.store'), quotationPayload(
            $this->client->id,
            $this->product->id,
            $this->variant->id,
        ))
        ->assertInvalid(['items.0.product_id']);
});

it('allows updating quotation status via service', function () {
    $quotation = Quotation::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->comercialUser->id,
        'status' => QuotationStatus::Draft,
    ]);

    app(QuotationService::class)->updateStatus($quotation, QuotationStatus::Sent);

    expect($quotation->fresh()->status)->toBe(QuotationStatus::Sent);
});

it('prevents changing status of converted quotation', function () {
    $order = SalesOrder::factory()->create();

    $quotation = Quotation::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->comercialUser->id,
        'status' => QuotationStatus::Accepted,
        'convert_to_order_id' => $order->id,
    ]);

    $this->actingAs($this->comercialUser)
        ->patch(route('quotations.update-status', $quotation), [
            'status' => QuotationStatus::Rejected->value,
        ])
        ->assertForbidden();
});

it('allows comercial to convert accepted quotation to order', function () {
    $quotation = Quotation::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->comercialUser->id,
        'status' => QuotationStatus::Accepted,
    ]);

    $quotation->items()->create([
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'type' => 'primer',
        'description' => 'Esmalte Industrial 2 en 1',
        'quantity' => 3,
        'list_unit_price' => 83940,
        'price_adjustment_pct' => 0,
        'unit_price' => 83940,
        'subtotal' => 251820,
        'sort_order' => 1,
    ]);

    $this->actingAs($this->comercialUser)
        ->post(route('quotations.convert-to-order', $quotation), [
            'priority' => 'high',
            'required_date' => now()->addDays(5)->format('Y-m-d'),
            'notes' => 'Notas del pedido',
            'shipping_address' => 'Calle 123 #45-67, Bogotá',
        ])
        ->assertRedirect();

    $order = SalesOrder::latest()->first();

    expect($order)->not->toBeNull();
    expect($order->client_id)->toBe($this->client->id);
    expect($order->status->value)->toBe('pending');
    expect($order->priority->value)->toBe('high');
    expect($order->required_date->format('Y-m-d'))->toBe(now()->addDays(5)->format('Y-m-d'));
    expect($order->notes)->toBe('Notas del pedido');
    expect($order->shipping_address)->toBe('Calle 123 #45-67, Bogotá');
    expect($order->quotation_id)->toBe($quotation->id);
    expect($order->created_by)->toBe($quotation->created_by);
    expect($order->items)->toHaveCount(1);

    $item = $order->items->first();
    expect($item->product_id)->toBe($this->product->id);
    expect($item->product_variant_id)->toBe($this->variant->id);
    expect((float) $item->quantity)->toEqual(3.0);

    $quotation->refresh();
    expect($quotation->convert_to_order_id)->toBe($order->id);
});

it('prevents converting non-accepted quotation', function () {
    $quotation = Quotation::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->comercialUser->id,
        'status' => QuotationStatus::Draft,
    ]);

    $this->actingAs($this->comercialUser)
        ->post(route('quotations.convert-to-order', $quotation), [
            'priority' => 'medium',
            'required_date' => now()->addDays(5)->format('Y-m-d'),
        ])
        ->assertForbidden();
});

it('prevents double conversion', function () {
    $order = SalesOrder::factory()->create();

    $quotation = Quotation::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->comercialUser->id,
        'status' => QuotationStatus::Accepted,
        'convert_to_order_id' => $order->id,
    ]);

    $this->actingAs($this->comercialUser)
        ->post(route('quotations.convert-to-order', $quotation), [
            'priority' => 'medium',
            'required_date' => now()->addDays(5)->format('Y-m-d'),
        ])
        ->assertForbidden();
});

it('prevents other comercial from converting quotation', function () {
    $quotation = Quotation::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->comercialUser->id,
        'status' => QuotationStatus::Accepted,
    ]);

    $this->actingAs($this->otherComercial)
        ->post(route('quotations.convert-to-order', $quotation), [
            'priority' => 'medium',
            'required_date' => now()->addDays(5)->format('Y-m-d'),
        ])
        ->assertForbidden();
});

it('exposes convertToOrder permission and salesOrderId on show', function () {
    $quotation = Quotation::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->comercialUser->id,
        'status' => QuotationStatus::Accepted,
    ]);

    $this->actingAs($this->comercialUser)
        ->get(route('quotations.show', $quotation))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Quotations/Show')
            ->where('can.convertToOrder', true)
            ->where('salesOrderId', null)
        );

    $order = SalesOrder::factory()->create(['created_by' => $this->comercialUser->id]);
    $quotation->update(['convert_to_order_id' => $order->id]);

    $this->actingAs($this->comercialUser)
        ->get(route('quotations.show', $quotation))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('can.convertToOrder', false)
            ->where('can.viewSalesOrder', true)
            ->where('salesOrderId', $order->id)
        );

    $otherOrder = SalesOrder::factory()->create(['created_by' => $this->otherComercial->id]);
    $quotation->update(['convert_to_order_id' => $otherOrder->id]);

    $this->actingAs($this->comercialUser)
        ->get(route('quotations.show', $quotation))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('can.viewSalesOrder', false)
            ->where('salesOrderId', $otherOrder->id)
        );
});

it('prevents converting quotation without items', function () {
    $quotation = Quotation::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->comercialUser->id,
        'status' => QuotationStatus::Accepted,
    ]);

    $this->actingAs($this->comercialUser)
        ->post(route('quotations.convert-to-order', $quotation), [
            'priority' => 'medium',
            'required_date' => now()->addDays(5)->format('Y-m-d'),
        ])
        ->assertSessionHasErrors(['status']);
});

it('allows admin to convert any accepted quotation to order', function () {
    $adminUser = User::factory()->create(['email_verified_at' => now()]);
    $adminUser->assignRole('admin');

    $quotation = Quotation::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->comercialUser->id,
        'status' => QuotationStatus::Accepted,
    ]);

    $quotation->items()->create([
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'type' => 'primer',
        'description' => 'Esmalte Industrial 2 en 1',
        'quantity' => 3,
        'list_unit_price' => 83940,
        'price_adjustment_pct' => 0,
        'unit_price' => 83940,
        'subtotal' => 251820,
        'sort_order' => 1,
    ]);

    $this->actingAs($adminUser)
        ->post(route('quotations.convert-to-order', $quotation), [
            'priority' => 'high',
            'required_date' => now()->addDays(5)->format('Y-m-d'),
            'notes' => 'Convertido por admin',
            'shipping_address' => 'Dirección admin',
        ])
        ->assertRedirect();

    $order = SalesOrder::latest()->first();

    expect($order)->not->toBeNull();
    expect($order->created_by)->toBe($quotation->created_by);
    expect($order->quotation_id)->toBe($quotation->id);
    expect($order->shipping_address)->toBe('Dirección admin');
});

it('rejects past required_date when converting quotation', function () {
    $quotation = Quotation::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->comercialUser->id,
        'status' => QuotationStatus::Accepted,
    ]);

    $quotation->items()->create([
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'type' => 'primer',
        'description' => 'Esmalte Industrial 2 en 1',
        'quantity' => 3,
        'list_unit_price' => 83940,
        'price_adjustment_pct' => 0,
        'unit_price' => 83940,
        'subtotal' => 251820,
        'sort_order' => 1,
    ]);

    $this->actingAs($this->comercialUser)
        ->post(route('quotations.convert-to-order', $quotation), [
            'priority' => 'medium',
            'required_date' => now()->subDays(3)->format('Y-m-d'),
        ])
        ->assertInvalid(['required_date']);
});

it('rejects invalid priority when converting quotation', function () {
    $quotation = Quotation::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->comercialUser->id,
        'status' => QuotationStatus::Accepted,
    ]);

    $quotation->items()->create([
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'type' => 'primer',
        'description' => 'Esmalte Industrial 2 en 1',
        'quantity' => 3,
        'list_unit_price' => 83940,
        'price_adjustment_pct' => 0,
        'unit_price' => 83940,
        'subtotal' => 251820,
        'sort_order' => 1,
    ]);

    $this->actingAs($this->comercialUser)
        ->post(route('quotations.convert-to-order', $quotation), [
            'priority' => 'invalid-priority',
            'required_date' => now()->addDays(5)->format('Y-m-d'),
        ])
        ->assertInvalid(['priority']);
});

it('allows converting quotation without shipping_address', function () {
    $quotation = Quotation::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->comercialUser->id,
        'status' => QuotationStatus::Accepted,
    ]);

    $quotation->items()->create([
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'type' => 'primer',
        'description' => 'Esmalte Industrial 2 en 1',
        'quantity' => 3,
        'list_unit_price' => 83940,
        'price_adjustment_pct' => 0,
        'unit_price' => 83940,
        'subtotal' => 251820,
        'sort_order' => 1,
    ]);

    $this->actingAs($this->comercialUser)
        ->post(route('quotations.convert-to-order', $quotation), [
            'priority' => 'medium',
            'required_date' => now()->addDays(5)->format('Y-m-d'),
        ])
        ->assertRedirect();

    $order = SalesOrder::latest()->first();

    expect($order)->not->toBeNull();
    expect($order->shipping_address)->toBeNull();
});
