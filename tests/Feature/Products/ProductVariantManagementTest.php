<?php

declare(strict_types=1);

namespace Tests\Feature\Products;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function setupVariantManagementDependencies(): array
{
    $role = Role::findOrCreate('admin');

    $user = User::factory()->create();
    $user->assignRole($role);

    $category = ProductCategory::create(['name' => 'Arquitectura']);
    $uom = UnitOfMeasure::create(['code' => 'GAL', 'name' => 'Galón', 'symbol' => 'gal']);

    $product = Product::create([
        'code' => 'VINIL-BASE',
        'name' => 'Vinil Base',
        'category_id' => $category->id,
        'unit_of_measure_id' => $uom->id,
        'price_threshold' => 3,
    ]);

    return [$user, $product, $uom];
}

test('admin can create a product variant', function () {
    [$user, $product, $uom] = setupVariantManagementDependencies();

    actingAs($user)
        ->post(route('products.variants.store', $product), [
            'sku' => 'VINIL-BASE-1GAL',
            'unit_of_measure_id' => $uom->id,
            'presentation_value' => 1,
            'presentation_label' => '1 gal',
            'color' => 'Blanco',
            'finish' => 'Mate',
            'base_type' => 'Agua',
            'component_system' => '1K',
            'current_cost' => 100,
            'current_price' => 140,
            'is_active' => true,
        ])
        ->assertRedirect(route('products.show', $product));

    $this->assertDatabaseHas('product_variants', [
        'product_id' => $product->id,
        'sku' => 'VINIL-BASE-1GAL',
    ]);
});

test('admin can update a product variant', function () {
    [$user, $product, $uom] = setupVariantManagementDependencies();

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'sku' => 'VINIL-BASE-1GAL',
        'unit_of_measure_id' => $uom->id,
        'component_system' => '1K',
    ]);

    actingAs($user)
        ->patch(route('products.variants.update', [$product, $variant]), [
            'sku' => 'VINIL-BASE-1GAL-V2',
            'unit_of_measure_id' => $uom->id,
            'presentation_value' => 1,
            'presentation_label' => '1 gal',
            'color' => 'Blanco',
            'finish' => 'Semi mate',
            'base_type' => 'Agua',
            'component_system' => '1K',
            'current_cost' => 105,
            'current_price' => 145,
            'is_active' => true,
        ])
        ->assertRedirect(route('products.show', $product));

    $variant->refresh();

    expect($variant->sku)->toBe('VINIL-BASE-1GAL-V2');
});

test('admin can delete a product variant', function () {
    [$user, $product, $uom] = setupVariantManagementDependencies();

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'sku' => 'VINIL-BASE-1GAL',
        'unit_of_measure_id' => $uom->id,
        'component_system' => '1K',
    ]);

    actingAs($user)
        ->delete(route('products.variants.destroy', [$product, $variant]))
        ->assertRedirect(route('products.show', $product));

    $this->assertSoftDeleted('product_variants', [
        'id' => $variant->id,
    ]);
});
