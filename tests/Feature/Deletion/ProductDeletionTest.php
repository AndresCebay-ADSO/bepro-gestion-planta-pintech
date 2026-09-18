<?php

declare(strict_types=1);

use App\Actions\Production\CreateProductionOrderAction;
use App\Enums\QrDocumentType;
use App\Enums\SystemRole;
use App\Models\FinishedInventory;
use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\ProductionOrder;
use App\Models\ProductVariant;
use App\Models\QuotationItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Productos y presentaciones: desactivar con historial, eliminar solo sin él (docs/POLITICA_ELIMINACION.md §3.1).
 */
function productDocumentFor(Product $product, User $uploader): ProductDocument
{
    Storage::disk('local')->put("product-documents/{$product->id}/ficha.pdf", 'pdf');

    return ProductDocument::create([
        'product_id' => $product->id,
        'document_type' => QrDocumentType::cases()[0],
        'file_name' => 'ficha.pdf',
        'file_path' => "product-documents/{$product->id}/ficha.pdf",
        'file_size' => 3,
        'mime_type' => 'application/pdf',
        'version' => 1,
        'is_current' => true,
        'uploaded_by' => $uploader->id,
    ]);
}

/**
 * @return array<string, mixed>
 */
function productUpdatePayload(Product $product, bool $isActive): array
{
    return [
        'code' => $product->code,
        'name' => $product->name,
        'category_id' => $product->category_id,
        'unit_of_measure_id' => $product->unit_of_measure_id,
        'is_active' => $isActive,
    ];
}

beforeEach(fn () => Storage::fake('local'));

it('elimina un producto sin historial junto con sus presentaciones y documentos', function () {
    $superAdmin = actingAsRole(SystemRole::SuperAdmin);
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    $document = productDocumentFor($product, $superAdmin);

    $this->delete(route('products.destroy', $product))->assertRedirect(route('products.index'));

    $this->assertDatabaseMissing('products', ['id' => $product->id]);
    $this->assertDatabaseMissing('product_variants', ['id' => $variant->id]);
    $this->assertDatabaseMissing('product_documents', ['id' => $document->id]);
    Storage::disk('local')->assertMissing($document->file_path);
});

it('no elimina un producto con historial y conserva presentaciones, documentos y archivos', function () {
    $superAdmin = actingAsRole(SystemRole::SuperAdmin);
    $product = Product::factory()->create();
    Formula::factory()->create(['product_id' => $product->id]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    $document = productDocumentFor($product, $superAdmin);

    $this->delete(route('products.destroy', $product))->assertSessionHas('error');

    $this->assertDatabaseHas('products', ['id' => $product->id]);
    $this->assertDatabaseHas('product_variants', ['id' => $variant->id]);
    $this->assertDatabaseHas('product_documents', ['id' => $document->id]);
    Storage::disk('local')->assertExists($document->file_path);
});

it('reserva la eliminación de productos a SuperAdmin', function () {
    actingAsRole(SystemRole::Admin);
    $product = Product::factory()->create();

    $this->delete(route('products.destroy', $product))->assertForbidden();

    $this->assertDatabaseHas('products', ['id' => $product->id]);
});

it('no desactiva un producto con órdenes en curso, pero sí con órdenes terminadas', function () {
    actingAsRole(SystemRole::Admin);
    $order = ProductionOrder::factory()->pending()->create();
    $product = $order->product;

    $this->put(route('products.update', $product), productUpdatePayload($product, false))->assertSessionHas('error');
    expect($product->fresh()->is_active)->toBeTrue();

    $order->update(['status' => 'completed']);

    $this->put(route('products.update', $product), productUpdatePayload($product, false))
        ->assertRedirect(route('products.index'));
    expect($product->fresh()->is_active)->toBeFalse();
});

it('elimina una presentación sin historial y rechaza una ya cotizada', function () {
    actingAsRole(SystemRole::Admin);
    $product = Product::factory()->create();
    $unused = ProductVariant::factory()->create(['product_id' => $product->id]);
    $quoted = ProductVariant::factory()->create(['product_id' => $product->id]);
    QuotationItem::factory()->create(['product_id' => $product->id, 'product_variant_id' => $quoted->id]);

    $this->delete(route('products.variants.destroy', [$product, $unused]))->assertRedirect(route('products.show', $product));
    $this->assertDatabaseMissing('product_variants', ['id' => $unused->id]);

    $this->delete(route('products.variants.destroy', [$product, $quoted]))->assertSessionHas('error');
    $this->assertDatabaseHas('product_variants', ['id' => $quoted->id]);
});

it('no desactiva una presentación con stock de producto terminado', function () {
    actingAsRole(SystemRole::Admin);
    $variant = ProductVariant::factory()->create();
    FinishedInventory::factory()->create(['product_variant_id' => $variant->id, 'quantity' => 5]);

    $this->patch(route('products.variants.update', [$variant->product_id, $variant]), [
        'code' => $variant->code,
        'name' => $variant->name,
        'unit_of_measure_id' => $variant->unit_of_measure_id,
        'presentation_value' => $variant->presentation_value,
        'presentation_label' => $variant->presentation_label,
        'is_active' => false,
    ])->assertSessionHas('error');

    expect($variant->fresh()->is_active)->toBeTrue();
});

it('rechaza crear una orden si su producto o su bodega se desactivaron después de validar', function (string $field) {
    $formula = Formula::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $user = User::factory()->create();

    match ($field) {
        'product_id' => $formula->product->update(['is_active' => false]),
        'warehouse_id' => $warehouse->update(['is_active' => false]),
    };

    expect(fn () => app(CreateProductionOrderAction::class)->execute([
        'product_id' => $formula->product_id,
        'formula_id' => $formula->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'planned_date' => now()->toDateString(),
    ], $user->id))->toThrow(ValidationException::class);

    expect(ProductionOrder::count())->toBe(0);
})->with(['product_id', 'warehouse_id']);
