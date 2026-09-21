<?php

declare(strict_types=1);

use App\Enums\QuotationStatus;
use App\Enums\SystemRole;
use App\Models\Client;
use App\Models\ProductVariant;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Una cotización abierta sigue su curso aunque se desactiven su cliente, sus productos o sus presentaciones
 * (docs/POLITICA_ELIMINACION.md §3.1).
 */

/**
 * @return array<string, mixed>
 */
function keptQuotationPayload(int $clientId, int $productId, int $variantId): array
{
    return [
        'client_id' => $clientId,
        'iva_percentage' => 19,
        'notes' => 'Notas editadas',
        'items' => [[
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'quantity' => 2,
            'price_adjustment_pct' => 0,
        ]],
    ];
}

beforeEach(function () {
    $this->admin = actingAsRole(SystemRole::Admin);
    $this->client = Client::factory()->create();
    $this->variant = ProductVariant::factory()->create(['current_price' => 100]);
    $this->quotation = Quotation::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->admin->id,
        'status' => QuotationStatus::Draft,
    ]);
    QuotationItem::factory()->create([
        'quotation_id' => $this->quotation->id,
        'product_id' => $this->variant->product_id,
        'product_variant_id' => $this->variant->id,
    ]);

    $this->client->update(['is_active' => false]);
    $this->variant->update(['is_active' => false]);
    $this->variant->product->update(['is_active' => false]);
});

it('edita una cotización en borrador aunque su cliente, producto y presentación estén inactivos', function () {
    $this->put(
        route('quotations.update', $this->quotation),
        keptQuotationPayload($this->client->id, $this->variant->product_id, $this->variant->id),
    )->assertSessionHasNoErrors();

    expect($this->quotation->fresh()->notes)->toBe('Notas editadas');
});

it('ofrece en el formulario de edición el cliente y la presentación inactivos que la cotización ya usa', function () {
    $this->get(route('quotations.edit', $this->quotation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('clients', fn ($clients) => collect($clients)->pluck('id')->contains($this->client->id))
            ->where('products', fn ($products) => collect($products)->pluck('variants')->flatten(1)->pluck('id')
                ->contains($this->variant->id)));

    // Al crear, en cambio, no aparecen.
    $this->get(route('quotations.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('clients', fn ($clients) => ! collect($clients)->pluck('id')->contains($this->client->id)));
});

it('no acepta al editar otro cliente ni otra presentación inactivos', function () {
    $otherClient = Client::factory()->create(['is_active' => false]);
    $otherVariant = ProductVariant::factory()->create(['is_active' => false]);

    $this->put(
        route('quotations.update', $this->quotation),
        keptQuotationPayload($otherClient->id, $otherVariant->product_id, $otherVariant->id),
    )->assertSessionHasErrors(['client_id', 'items.0.product_variant_id']);
});
