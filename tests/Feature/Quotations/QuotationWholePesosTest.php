<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Quotation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * PR 4b de la auditoría 3.6: la cotización en pesos enteros. Se redondea una sola vez y antes de calcular, así que lo
 * que ve el cliente cuadra con una calculadora: línea = cantidad × precio, total = subtotal + IVA.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->commercial = User::factory()->create();
    $this->commercial->assignRole(SystemRole::Commercial->value);

    $unit = UnitOfMeasure::factory()->create();
    $category = ProductCategory::create(['name' => 'Cat pesos enteros']);
    // Sin margen: el precio de lista es el precio de la presentación.
    $this->product = Product::create(['name' => 'Producto pesos enteros', 'category_id' => $category->id,
        'unit_of_measure_id' => $unit->id, 'sales_margin' => 0, 'cif_percentage' => 15, 'price_threshold' => 3,
        'current_price' => 1000, 'is_active' => true]);
    $this->variant = ProductVariant::create(['product_id' => $this->product->id, 'code' => 'VAR-PESOS',
        'name' => 'Galón', 'unit_of_measure_id' => $unit->id, 'presentation_value' => 1, 'current_price' => '1000.5',
        'is_active' => true]);
    $this->client = Client::factory()->create();
});

/**
 * @param  array<string, mixed>  $item
 * @return array<string, mixed>
 */
function wholePesosPayload(object $t, array $item, string $iva = '19'): array
{
    return [
        'client_id' => $t->client->id,
        'quotation_date' => plantToday(),
        'iva_percentage' => $iva,
        'items' => [array_replace([
            'product_id' => $t->product->id,
            'product_variant_id' => $t->variant->id,
            'type' => 'primer',
            'description' => 'Esmalte',
            'quantity' => 5,
            'price_adjustment_pct' => 0,
        ], $item)],
    ];
}

function storedQuotation(): Quotation
{
    return Quotation::query()->with('items')->sole();
}

test('el precio unitario se guarda en pesos enteros, con la mitad hacia arriba', function (string $variantPrice, string $adjustment, string $expectedUnit): void {
    $this->variant->update(['current_price' => $variantPrice]);

    $this->actingAs($this->commercial)
        ->post(route('quotations.store'), wholePesosPayload($this, ['price_adjustment_pct' => $adjustment]))
        ->assertSessionHasNoErrors();

    expect(bccomp((string) storedQuotation()->items->sole()->unit_price, $expectedUnit, 4))->toBe(0);
})->with([
    'lista 1000,5 sube' => ['1000.5', '0', '1001'],
    'lista 1000,4 baja' => ['1000.4', '0', '1000'],
    // 1000 / (1 − 0,15) = 1176,47…
    'ajuste +15 %' => ['1000', '15', '1176'],
    // 1000 / (1 + 0,0588) = 944,46…
    'ajuste −5,88 %' => ['1000', '-5.88', '944'],
]);

test('la línea, el subtotal, el IVA y el total cuadran con una calculadora', function (): void {
    // El caso del navegador: 5 × 9.816.127 debía dar 49.080.635 y la cotización mostraba 49.080.633.
    $this->actingAs($this->commercial)
        ->post(route('quotations.store'), wholePesosPayload($this, ['unit_price' => '9816127']))
        ->assertSessionHasNoErrors();

    $quotation = storedQuotation();
    $item = $quotation->items->sole();

    expect(bccomp((string) $item->subtotal, '49080635', 4))->toBe(0)
        ->and(bccomp((string) $quotation->subtotal, '49080635', 4))->toBe(0)
        // 49.080.635 × 19 % = 9.325.320,65 → 9.325.321
        ->and(bccomp((string) $quotation->iva_amount, '9325321', 4))->toBe(0)
        ->and(bccomp((string) $quotation->total, '58405956', 4))->toBe(0);
});

test('el precio digitado a mano va en pesos enteros', function (): void {
    $this->actingAs($this->commercial)
        ->post(route('quotations.store'), wholePesosPayload($this, ['unit_price' => '1234.56']))
        ->assertSessionHasErrors('items.0.unit_price');
});

test('el IVA lleva como mucho 2 decimales', function (): void {
    $this->actingAs($this->commercial)
        ->post(route('quotations.store'), wholePesosPayload($this, [], '19.999'))
        ->assertSessionHasErrors('iva_percentage');
});

test('una línea que no cabe en su columna da un error de validación y no un 500 (COT-02)', function (): void {
    $this->actingAs($this->commercial)
        ->post(route('quotations.store'), wholePesosPayload($this, ['quantity' => 2000000, 'unit_price' => '999999999']))
        ->assertSessionHasErrors('items.0.quantity');

    expect(Quotation::query()->count())->toBe(0);
});

test('la vista previa devuelve lo mismo que se guarda', function (): void {
    $payload = wholePesosPayload($this, ['price_adjustment_pct' => '15']);

    $preview = $this->actingAs($this->commercial)
        ->postJson(route('quotations.preview-pricing'), $payload)
        ->assertOk()
        ->json();

    $this->actingAs($this->commercial)->post(route('quotations.store'), $payload)->assertSessionHasNoErrors();
    $quotation = storedQuotation();
    $item = $quotation->items->sole();

    expect(bccomp($preview['items'][0]['unit_price'], (string) $item->unit_price, 4))->toBe(0)
        ->and(bccomp($preview['items'][0]['subtotal'], (string) $item->subtotal, 4))->toBe(0)
        ->and(bccomp($preview['totals']['iva_amount'], (string) $quotation->iva_amount, 4))->toBe(0)
        ->and(bccomp($preview['totals']['total'], (string) $quotation->total, 4))->toBe(0);
});

test('la vista previa rechaza datos inválidos con 422 y pide permiso de crear', function (): void {
    $this->actingAs($this->commercial)
        ->postJson(route('quotations.preview-pricing'), wholePesosPayload($this, ['unit_price' => '10.5']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items.0.unit_price');

    $this->actingAs(userWithRole(SystemRole::Operator))
        ->postJson(route('quotations.preview-pricing'), wholePesosPayload($this, []))
        ->assertForbidden();
});

test('si el ajuste y el precio llegan juntos y el ajuste da ese precio, se conserva el ajuste digitado', function (): void {
    $this->variant->update(['current_price' => '1000']);

    // 1000 / 0,85 = 1176,47 → 1176; recalculado desde el precio daría 14,97 %.
    $this->actingAs($this->commercial)
        ->post(route('quotations.store'), wholePesosPayload($this, ['price_adjustment_pct' => '15', 'unit_price' => '1176']))
        ->assertSessionHasNoErrors();

    $item = storedQuotation()->items->sole();

    expect((string) $item->price_adjustment_pct)->toBe('15.00')
        ->and(bccomp((string) $item->unit_price, '1176', 4))->toBe(0);
});
