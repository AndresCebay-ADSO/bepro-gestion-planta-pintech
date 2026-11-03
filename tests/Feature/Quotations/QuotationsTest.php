<?php

declare(strict_types=1);

use App\Actions\Quotations\BuildQuotationPdfDataAction;
use App\Actions\Quotations\RenderQuotationPdfAction;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Enums\QuotationItemType;
use App\Enums\QuotationStatus;
use App\Enums\QuotationValidity;
use App\Enums\SystemRole;
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
use App\Support\EnumOptions;
use App\Support\ImageDataUri;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->unit = UnitOfMeasure::factory()->create();

    $this->category = ProductCategory::create([
        'name' => 'Categoría Test',
    ]);

    $this->product = Product::create([
        'name' => 'Esmalte Dualtech',
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
    $this->comercialUser->assignRole(SystemRole::Commercial->value);

    $this->otherComercial = User::factory()->create(['email_verified_at' => now()]);
    $this->otherComercial->assignRole(SystemRole::Commercial->value);

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
        'quotation_date' => plantToday(),
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

/**
 * Cotización con textos fijos y realistas para los tests que cuentan hojas del PDF. La factory inventa el nombre del
 * cliente y deja el teléfono del asesor a veces sí y a veces no; un renglón de más o de menos volvía esos tests una
 * lotería (fallaban 4 de cada 10 corridas).
 */
function quotationWithFixedPdfTexts(int $clientId, User $advisor, array $overrides = []): Quotation
{
    $advisor->update([
        'name' => 'Andrea Catalina Rodríguez',
        'job_title' => 'Asesora comercial',
        'phone' => '317 404 1993',
        'email' => 'asesora.comercial@example.org',
    ]);

    return Quotation::factory()->create([
        'client_id' => $clientId,
        'created_by' => $advisor->id,
        'client_business_name' => 'Constructora e Inversiones del Pacífico S.A.S',
        'client_nit' => '9001234567',
        'client_contact_name' => 'María Fernanda Ospina',
        'client_phone' => '315 678 9012',
        'technology' => 'Epóxico',
        'line' => 'Industrial Series',
        'thickness_mils' => '8',
        'application_method' => 'Airless',
        'delivery_time' => '5 Días Hab.',
        'area' => '1250',
        'notes' => null,
        ...$overrides,
    ]);
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
    // El precio de lista conserva sus decimales; el precio de la cotización va en pesos enteros (PR 4b).
    expect((float) $item->list_unit_price)->toEqual($expectedListPrice);
    expect((float) $item->unit_price)->toEqual(round($expectedListPrice));
    expect((float) $item->subtotal)->toEqual(round($expectedListPrice) * 3);
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
    expect((float) $item->unit_price)->toEqual(round($listPrice / 1.1));
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

it('sends the fixed choices of the quotation form as select options', function (string $routeName) {
    $quotation = Quotation::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->comercialUser->id,
        'status' => QuotationStatus::Draft,
    ]);

    $url = $routeName === 'quotations.edit' ? route($routeName, $quotation) : route($routeName);

    $this->actingAs($this->comercialUser)
        ->get($url)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('validityDaysOptions', EnumOptions::for(QuotationValidity::cases()))
            ->where('paymentMethodOptions', EnumOptions::for(PaymentMethod::cases()))
            ->where('itemTypeOptions', EnumOptions::for(QuotationItemType::cases()))
            ->where('validityDaysOptions.0', fn ($option) => array_keys($option->all()) === ['value', 'label'])
        );
})->with(['quotations.create', 'quotations.edit']);

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

    $response = $this->actingAs($this->comercialUser)
        ->get(route('quotations.export-pdf', $quotation))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect($response->headers->get('content-disposition'))
        ->toStartWith('inline;')
        ->toContain('.pdf');
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
        ->toContain('Cotización No. 4415')
        ->toContain('www.beprocoatings.com')
        ->toContain('Sistema ofertado')
        ->toContain('Condiciones comerciales')
        ->toContain('Producto / Descripción')
        ->toContain('SEDE CALI')
        ->toContain('Calle 23 No. 17-100')
        ->toContain('SEDE NEIVA')
        ->toContain('Calle 4 No. 2-06')
        ->toContain('Notas y alcance')
        ->toContain('Móvil: 3009876543')
        // Se busca el atributo y no el nombre suelto: las clases también aparecen en el CSS de la plantilla.
        ->toContain('class="closing closing-stacked"')
        ->not->toContain('class="closing closing-side"');
});

it('prints quotation pdf with decimal iva percentage formatted as percentage points (B46)', function () {
    $quotation = Quotation::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->comercialUser->id,
        'iva_percentage' => '19.50',
    ]);

    $quotationData = app(BuildQuotationPdfDataAction::class)->execute($quotation);

    $html = view('pdf.quotation', [
        'quotation' => $quotationData,
        'beproLogoBase64' => null,
        'pintechLogoBase64' => null,
        'generatedAt' => now()->format('d/m/Y H:i'),
    ])->render();

    expect($html)->toContain('IVA 19,5%');
});

it('prints the commercial observations apart from the legal fine print, only when there are any', function (?string $notes, bool $printed) {
    $quotation = Quotation::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->comercialUser->id,
        'notes' => $notes,
    ]);

    $html = view('pdf.quotation', [
        'quotation' => app(BuildQuotationPdfDataAction::class)->execute($quotation),
        'beproLogoBase64' => null,
        'pintechLogoBase64' => null,
    ])->render();

    expect($html)->toContain('Notas y alcance');
    expect(str_contains($html, 'Observaciones:'))->toBe($printed);

    if ($printed) {
        expect($html)->toContain('No incluye transporte.<br />');
    }
})->with([
    'con observación' => ["No incluye transporte.\nAnticipo del 50 %.", true],
    'sin observación' => [null, false],
    'solo espacios' => ['   ', false],
]);

it('lets long observations flow instead of pushing the signature block to the next page', function () {
    // El bloque de notas y firma no se parte entre hojas; si las observaciones vivieran dentro de él, un texto largo lo
    // empujaría entero a la hoja 2 y dejaría la primera a medio llenar.
    $quotation = quotationWithFixedPdfTexts($this->client->id, $this->comercialUser, [
        'notes' => str_repeat('Condición particular de este negocio acordada con el cliente. ', 24),
    ]);

    foreach (range(1, 4) as $position) {
        $quotation->items()->create([
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'type' => 'primer',
            'description' => 'Imprimante epóxico anticorrosivo de alto espesor',
            'quantity' => 12,
            'list_unit_price' => 685000,
            'price_adjustment_pct' => 0,
            'unit_price' => 685000,
            'subtotal' => 8220000,
            'sort_order' => $position,
        ]);
    }

    $pdf = app(RenderQuotationPdfAction::class)->execute($quotation);

    expect($pdf->getDomPDF()->getCanvas()->get_page_count())->toBe(1);
});

it('fits eight products with long names on a single letter page', function () {
    // El jefe comercial: en promedio 4 a 6 productos, fácilmente 8. Con nombres largos del catálogo la firma ya no
    // cabe debajo de las notas y RenderQuotationPdfAction las pone lado a lado para no pasar a otra hoja.
    $this->product->update(['name' => 'BP PRIMER EPÓXICO ESTRUCTURAL 60% SÓLIDOS GRIS CLARO']);

    $quotation = quotationWithFixedPdfTexts($this->client->id, $this->comercialUser, [
        'notes' => 'Incluye visita técnica de inicio de obra.',
    ]);

    foreach (range(1, 8) as $position) {
        $quotation->items()->create([
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'type' => 'primer',
            'description' => 'Imprimante epóxico anticorrosivo de alto espesor',
            'color' => 'Negro RAL 9006',
            'quantity' => 12,
            'list_unit_price' => 685000,
            'price_adjustment_pct' => 0,
            'unit_price' => 685000,
            'subtotal' => 8220000,
            'sort_order' => $position,
        ]);
    }

    // Precondición: con la firma debajo estos datos ocupan 2 hojas, así que la hoja única solo sale del cambio a lado a
    // lado. Si un día caben en 1, el test ya no prueba ese cambio y hay que alargar los datos.
    $stacked = Pdf::loadView('pdf.quotation', [
        'quotation' => app(BuildQuotationPdfDataAction::class)->execute($quotation),
        'beproLogoBase64' => ImageDataUri::fromFile(public_path(config('quotation.pdf_logos.header'))),
        'pintechLogoBase64' => ImageDataUri::fromFile(public_path(config('quotation.pdf_logos.footer'))),
        'closingLayout' => RenderQuotationPdfAction::LAYOUT_STACKED,
    ])->setPaper('letter');
    $stacked->render();

    expect($stacked->getDomPDF()->getCanvas()->get_page_count())
        ->toBe(2, 'Con la firma debajo los datos ya caben en una hoja: alárgalos para seguir probando el cambio a lado a lado.');

    $pdf = app(RenderQuotationPdfAction::class)->execute($quotation);

    expect($pdf->getDomPDF()->getCanvas()->get_page_count())->toBe(1);
});

it('keeps the quotation pdf logos small and without transparency', function (string $logo) {
    $path = public_path(config("quotation.pdf_logos.{$logo}"));

    expect($path)->toBeFile();

    [$width, $height] = getimagesize($path);
    // Byte 25 del PNG (IHDR) es el tipo de color: 2 = RGB, 6 = RGBA. DomPDF sin Imagick recorre el canal alfa
    // píxel por píxel con GD y un logo grande con transparencia agota los 128 MB del servidor.
    $pngColorType = ord(file_get_contents($path, false, null, 25, 1));

    expect($width)->toBeLessThanOrEqual(600)
        ->and($height)->toBeLessThanOrEqual(600)
        ->and($pngColorType)->not->toBe(6);
})->with(['header', 'footer']);

it('signs the quotation pdf with the signature of the user who created it', function () {
    Storage::fake(User::SIGNATURE_DISK);
    Storage::disk(User::SIGNATURE_DISK)->put(
        'signatures/asesor.png',
        UploadedFile::fake()->image('firma.png', 200, 50)->getContent(),
    );
    $this->comercialUser->update(['signature_path' => 'signatures/asesor.png']);

    $quotation = Quotation::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->comercialUser->id,
    ]);

    $signature = app(BuildQuotationPdfDataAction::class)->execute($quotation)['advisor']['signature'];

    expect($signature)->toStartWith('data:image/png;base64,');

    $response = $this->actingAs($this->comercialUser)
        ->get(route('quotations.export-pdf', $quotation))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect($response->headers->get('content-disposition'))
        ->toStartWith('inline;')
        ->toContain('.pdf');
});

it('leaves the advisor signature empty when the creator has none or the file is missing', function (?string $path) {
    Storage::fake(User::SIGNATURE_DISK);
    $this->comercialUser->update(['signature_path' => $path]);

    $quotation = Quotation::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->comercialUser->id,
    ]);

    expect(app(BuildQuotationPdfDataAction::class)->execute($quotation)['advisor']['signature'])->toBeNull();
})->with([
    'sin firma' => [null],
    'archivo inexistente' => ['signatures/borrada.png'],
]);

it('formats the free-text area and thickness for the quotation pdf', function (?string $area, ?string $thickness, string $expectedArea, string $expectedThickness) {
    $quotation = Quotation::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->comercialUser->id,
        'area' => $area,
        'thickness_mils' => $thickness,
    ]);

    $data = app(BuildQuotationPdfDataAction::class)->execute($quotation);

    expect($data['area'])->toBe($expectedArea)
        ->and($data['thickness_mils'])->toBe($expectedThickness);
})->with([
    'vacíos' => [null, '', 'N.A.', 'N.A.'],
    'solo números' => ['5750', '4', '5.750', '4 Mils'],
    'espesor decimal' => ['600', '3,5', '600', '3,5 Mils'],
    'texto libre' => ['600 m2 aprox', '4 Mils', '600 m2 aprox', '4 Mils'],
]);

// Las cantidades de la cotización son unidades enteras (PR 4 de 3.6); el PDF las imprime con separador de miles. La
// acción entrega el número sin formato y lo formatea solo la vista: formatearlo dos veces leía «1.250» como 1,25.
it('prints item quantities with the thousands separator', function (string $quantity, string $expected) {
    $quotation = Quotation::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->comercialUser->id,
    ]);

    $quotation->items()->create([
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'type' => 'primer',
        'quantity' => $quantity,
        'list_unit_price' => 1000,
        'price_adjustment_pct' => 0,
        'unit_price' => 1000,
        'subtotal' => 1000,
        'sort_order' => 1,
    ]);

    $html = view('pdf.quotation', [
        'quotation' => app(BuildQuotationPdfDataAction::class)->execute($quotation),
        'beproLogoBase64' => null,
        'pintechLogoBase64' => null,
        'generatedAt' => now()->format('d/m/Y H:i'),
    ])->render();

    expect($html)->toContain("<td>{$expected}</td>");
})->with([
    'entero' => ['55', '55'],
    'miles' => ['1250', '1.250'],
    'decenas de miles' => ['12000', '12.000'],
]);

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

it('fails validation when product is inactive', function () {
    $this->product->update(['is_active' => false]);

    $this->actingAs($this->comercialUser)
        ->post(route('quotations.store'), quotationPayload(
            $this->client->id,
            $this->product->id,
            $this->variant->id,
        ))
        ->assertInvalid(['items.0.product_id']);
});

it('fails validation when client is inactive', function () {
    $this->client->update(['is_active' => false]);

    $this->actingAs($this->comercialUser)
        ->post(route('quotations.store'), quotationPayload(
            $this->client->id,
            $this->product->id,
            $this->variant->id,
        ))
        ->assertInvalid(['client_id']);
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

// 3.4: el color que pidió el cliente pasa de cada ítem de la cotización al pedido.
it('carries the color of each quotation item to the order', function () {
    $quotation = Quotation::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->comercialUser->id,
        'status' => QuotationStatus::Accepted,
    ]);
    $item = fn (int $sortOrder, ?string $color) => $quotation->items()->create([
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'type' => 'primer',
        'quantity' => 1,
        'color' => $color,
        'list_unit_price' => 83940,
        'price_adjustment_pct' => 0,
        'unit_price' => 83940,
        'subtotal' => 83940,
        'sort_order' => $sortOrder,
    ]);
    $item(1, 'RAL 3020');
    $item(2, null);

    $this->actingAs($this->comercialUser)
        ->post(route('quotations.convert-to-order', $quotation), [
            'priority' => 'high',
            'shipping_address' => 'Calle 1 # 2-3, Cali',
            'required_date' => now()->addDays(5)->format('Y-m-d'),
        ])
        ->assertRedirect();

    expect(SalesOrder::latest('id')->first()->items()->orderBy('id')->pluck('color')->all())->toBe(['RAL 3020', null]);
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
            'shipping_address' => 'Calle 1 # 2-3, Cali',
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
            'shipping_address' => 'Calle 1 # 2-3, Cali',
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

it('requires the shipping_address to convert a quotation (D-CLI3)', function () {
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
        ->assertSessionHasErrors('shipping_address');

    expect(SalesOrder::query()->count())->toBe(0);
});

it('requires sales_orders.create to convert a quotation into an order', function () {
    $role = Role::findOrCreate('cotizador', 'web');
    $role->syncPermissions([
        Permission::QuotationsViewOwn->value,
        Permission::QuotationsConvertToOrder->value,
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    $quotation = Quotation::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $user->id,
        'status' => QuotationStatus::Accepted,
    ]);

    $this->actingAs($user)
        ->post(route('quotations.convert-to-order', $quotation), [
            'priority' => 'medium',
            'required_date' => now()->addDays(5)->format('Y-m-d'),
        ])
        ->assertForbidden();

    expect($quotation->fresh()->convert_to_order_id)->toBeNull();

    // Contraprueba: con sales_orders.create la policy sí lo permite, así que el 403 venía de ese permiso.
    $role->givePermissionTo(Permission::SalesOrdersCreate->value);

    expect(Gate::forUser($user->fresh())->allows('convertToOrder', $quotation->fresh()))->toBeTrue();
});

// Auditoría 3.6, PR 5: COT-05. Un ID que no es entero llegaba al `exists` contra un bigint: 500 en PostgreSQL.
test('cliente y producto que no son enteros dan un error del campo, también en la vista previa', function (string $field, mixed $id): void {
    $payload = quotationPayload($this->client->id, $this->product->id, $this->variant->id);
    data_set($payload, $field, $id);

    $this->actingAs($this->comercialUser)
        ->post(route('quotations.store'), $payload)
        ->assertSessionHasErrors($field);

    if ($field !== 'client_id') {
        $this->actingAs($this->comercialUser)
            ->postJson(route('quotations.preview-pricing'), $payload)
            ->assertJsonValidationErrors($field);
    }
})->with([
    'cliente' => ['client_id'],
    'producto' => ['items.0.product_id'],
    'presentación' => ['items.0.product_variant_id'],
])->with([
    'texto' => ['abc'],
    'mayor que un bigint' => ['9223372036854775808'],
    'arreglo' => [[1]],
]);
