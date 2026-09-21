<?php

declare(strict_types=1);

use App\Actions\Production\CreateProductionOrderAction;
use App\Enums\Permission;
use App\Enums\QrDocumentType;
use App\Enums\SystemRole;
use App\Models\FinishedInventory;
use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderPackagingPlan;
use App\Models\ProductVariant;
use App\Models\QuotationItem;
use App\Models\RawMaterial;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

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

/**
 * @return array<string, mixed>
 */
function variantUpdatePayload(ProductVariant $variant, bool $isActive): array
{
    return [
        'code' => $variant->code,
        'name' => $variant->name,
        'unit_of_measure_id' => $variant->unit_of_measure_id,
        'presentation_value' => $variant->presentation_value,
        'presentation_label' => $variant->presentation_label,
        'package_raw_material_id' => $variant->package_raw_material_id,
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

it('muestra la casilla de estado del producto solo a quien puede desactivarlo', function () {
    $product = Product::factory()->create();

    actingAsRole(SystemRole::Admin);
    $this->get(route('products.edit', $product))
        ->assertInertia(fn (Assert $page) => $page->where('can.deactivate', true));

    // Rol personalizado que edita productos pero no los desactiva.
    $editor = User::factory()->create();
    $editor->givePermissionTo([Permission::DashboardView->value, Permission::ProductsView->value, Permission::ProductsEdit->value]);
    $this->actingAs($editor);

    $this->get(route('products.edit', $product))
        ->assertInertia(fn (Assert $page) => $page->where('can.deactivate', false));

    $this->put(route('products.update', $product), [...productUpdatePayload($product, true), 'name' => 'Nombre nuevo'])
        ->assertRedirect(route('products.index'));
    expect($product->fresh()->name)->toBe('Nombre nuevo');
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

    $this->patch(route('products.variants.update', [$variant->product_id, $variant]), variantUpdatePayload($variant, false))
        ->assertSessionHas('error');

    expect($variant->fresh()->is_active)->toBeTrue();
});

it('no desactiva un producto con stock de producto terminado', function () {
    actingAsRole(SystemRole::Admin);
    $inventory = FinishedInventory::factory()->create(['quantity' => 5]);
    $product = $inventory->product;

    $this->put(route('products.update', $product), productUpdatePayload($product, false))->assertSessionHas('error');
    expect($product->fresh()->is_active)->toBeTrue();

    $inventory->update(['quantity' => 0]);

    $this->put(route('products.update', $product), productUpdatePayload($product, false))
        ->assertRedirect(route('products.index'));
    expect($product->fresh()->is_active)->toBeFalse();
});

it('no desactiva una presentación que una orden en curso va a envasar, pero sí si la orden terminó', function () {
    actingAsRole(SystemRole::Admin);
    $order = ProductionOrder::factory()->inProgress()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $order->product_id]);
    ProductionOrderPackagingPlan::create([
        'production_order_id' => $order->id,
        'product_variant_id' => $variant->id,
        'planned_units' => 10,
    ]);

    $this->patch(route('products.variants.update', [$variant->product_id, $variant]), variantUpdatePayload($variant, false))
        ->assertSessionHas('error');
    expect($variant->fresh()->is_active)->toBeTrue();

    $order->update(['status' => 'completed']);

    $this->patch(route('products.variants.update', [$variant->product_id, $variant]), variantUpdatePayload($variant, false))
        ->assertSessionMissing('error');
    expect($variant->fresh()->is_active)->toBeFalse();
});

it('ofrece el envase inactivo solo junto a la presentación que ya lo usa, y la edita sin cambiarlo', function () {
    actingAsRole(SystemRole::Admin);
    $product = Product::factory()->create();
    $kept = RawMaterial::factory()->create(['is_active' => false]);
    $otherInactive = RawMaterial::factory()->create(['is_active' => false]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'package_raw_material_id' => $kept->id]);

    $this->get(route('products.show', $product))->assertInertia(fn (Assert $page) => $page
        ->where('rawMaterials', fn ($options) => collect($options)->contains(fn ($o) => $o['id'] === $kept->id && $o['is_active'] === false)
            && ! collect($options)->contains('id', $otherInactive->id)));

    $this->patch(route('products.variants.update', [$product, $variant]), [
        ...variantUpdatePayload($variant, true),
        'name' => 'Nombre nuevo',
    ])->assertSessionHasNoErrors();

    expect($variant->fresh())
        ->name->toBe('Nombre nuevo')
        ->package_raw_material_id->toBe($kept->id);
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
