<?php

declare(strict_types=1);

use App\Enums\QrDocumentType;
use App\Enums\RemnantStatus;
use App\Enums\SystemRole;
use App\Models\Formula;
use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\ProductionOrder;
use App\Models\ProductionRemnant;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Storage;

/**
 * Bodegas, fórmulas y documentos de producto (docs/POLITICA_ELIMINACION.md §3.1 y §3.4).
 */

/**
 * @return array<string, mixed>
 */
function warehouseUpdatePayload(Warehouse $warehouse, bool $isActive): array
{
    return [
        'name' => $warehouse->name,
        'city' => $warehouse->city ?? 'Bogotá',
        'address' => $warehouse->address,
        'is_active' => $isActive,
    ];
}

it('elimina una bodega sin historial y rechaza una con lotes', function () {
    actingAsRole(SystemRole::SuperAdmin);
    $unused = Warehouse::factory()->create();
    $withBatches = Warehouse::factory()->create();
    InventoryBatch::factory()->create(['warehouse_id' => $withBatches->id]);

    $this->delete(route('warehouses.destroy', $unused))->assertRedirect(route('warehouses.index'));
    $this->assertDatabaseMissing('warehouses', ['id' => $unused->id]);

    $this->delete(route('warehouses.destroy', $withBatches))->assertSessionHas('error');
    $this->assertDatabaseHas('warehouses', ['id' => $withBatches->id]);
});

it('no desactiva una bodega con stock de materia prima', function () {
    actingAsRole(SystemRole::Admin);
    $warehouse = Warehouse::factory()->create();
    InventoryBatch::factory()->create(['warehouse_id' => $warehouse->id, 'remaining_quantity' => 10, 'initial_quantity' => 10]);

    $this->put(route('warehouses.update', $warehouse), warehouseUpdatePayload($warehouse, false))->assertSessionHas('error');

    expect($warehouse->fresh()->is_active)->toBeTrue();
});

it('no desactiva una bodega con órdenes en curso y sí una sin stock ni órdenes', function () {
    actingAsRole(SystemRole::Admin);
    $order = ProductionOrder::factory()->pending()->create();
    $busy = $order->warehouse;
    $idle = Warehouse::factory()->create();

    $this->put(route('warehouses.update', $busy), warehouseUpdatePayload($busy, false))->assertSessionHas('error');
    expect($busy->fresh()->is_active)->toBeTrue();

    $this->put(route('warehouses.update', $idle), warehouseUpdatePayload($idle, false))->assertRedirect(route('warehouses.index'));
    expect($idle->fresh()->is_active)->toBeFalse();
});

it('no desactiva una bodega con saldos de producción disponibles, pero sí con saldos consumidos', function () {
    $admin = actingAsRole(SystemRole::Admin);
    $order = ProductionOrder::factory()->completed()->create();
    $warehouse = $order->warehouse;
    $remnant = ProductionRemnant::factory()->create([
        'source_order_id' => $order->id,
        'product_id' => $order->product_id,
        'warehouse_id' => $warehouse->id,
        'created_by' => $admin->id,
    ]);

    $this->put(route('warehouses.update', $warehouse), warehouseUpdatePayload($warehouse, false))->assertSessionHas('error');
    expect($warehouse->fresh()->is_active)->toBeTrue();

    $remnant->update([
        'available_quantity_gallons' => 0,
        'available_quantity_kg' => 0,
        'status' => RemnantStatus::Consumed,
    ]);

    $this->put(route('warehouses.update', $warehouse), warehouseUpdatePayload($warehouse, false))
        ->assertRedirect(route('warehouses.index'));
    expect($warehouse->fresh()->is_active)->toBeFalse();
});

it('elimina una fórmula sin historial con sus ingredientes y rechaza una usada en una orden', function () {
    actingAsRole(SystemRole::SuperAdmin);
    $unused = Formula::factory()->create();
    $used = ProductionOrder::factory()->create()->formula;

    $this->delete(route('formulas.destroy', $unused))->assertRedirect(route('formulas.index'));
    $this->assertDatabaseMissing('formulas', ['id' => $unused->id]);

    $this->delete(route('formulas.destroy', $used))->assertSessionHas('error');
    $this->assertDatabaseHas('formulas', ['id' => $used->id]);
});

it('elimina un documento de producto con su archivo y lo registra en la auditoría', function () {
    Storage::fake('local');
    $admin = actingAsRole(SystemRole::Admin);
    $product = Product::factory()->create();
    Storage::disk('local')->put('product-documents/ficha.pdf', 'pdf');
    $document = ProductDocument::create([
        'product_id' => $product->id,
        'document_type' => QrDocumentType::cases()[0],
        'file_name' => 'ficha.pdf',
        'file_path' => 'product-documents/ficha.pdf',
        'file_size' => 3,
        'mime_type' => 'application/pdf',
        'version' => 1,
        'is_current' => true,
        'uploaded_by' => $admin->id,
    ]);

    $this->delete(route('products.documents.destroy', $document))->assertRedirect();

    $this->assertDatabaseMissing('product_documents', ['id' => $document->id]);
    Storage::disk('local')->assertMissing('product-documents/ficha.pdf');
    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => ProductDocument::class,
        'subject_id' => $document->id,
        'event' => 'deleted',
    ]);
});
