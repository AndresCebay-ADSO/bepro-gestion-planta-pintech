<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('admin');
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->category = ProductCategory::create(['name' => 'Industrial']);
    $this->uom = UnitOfMeasure::create(['code' => 'GAL', 'name' => 'Galón', 'symbol' => 'gal']);
    $this->product = Product::create([
        'code' => 'TEST-QR',
        'name' => 'Producto prueba calidad',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->uom->id,
        'price_threshold' => 3,
        'cif_percentage' => 10,
    ]);
});

test('product update rejects solids range when lower exceeds upper', function (): void {
    actingAs($this->user)
        ->put(route('products.update', $this->product), [
            'code' => 'TEST-QR',
            'name' => 'Producto prueba calidad',
            'brand' => 'BEPRO',
            'description' => null,
            'category_id' => $this->category->id,
            'unit_of_measure_id' => $this->uom->id,
            'current_cost' => '',
            'cif_percentage' => '10',
            'current_price' => '',
            'price_threshold' => '3',
            'quality_viscosity_lower' => '',
            'quality_viscosity_upper' => '',
            'quality_fineness_lower' => '',
            'quality_fineness_upper' => '',
            'quality_solids_lower' => '60',
            'quality_solids_upper' => '50',
            'is_active' => true,
        ])
        ->assertSessionHasErrors('quality_solids_upper');
});

test('product update persists quality reference ranges', function (): void {
    actingAs($this->user)
        ->put(route('products.update', $this->product), [
            'code' => 'TEST-QR',
            'name' => 'Producto prueba calidad',
            'brand' => 'PINTECH',
            'description' => 'Texto de descripción',
            'category_id' => $this->category->id,
            'unit_of_measure_id' => $this->uom->id,
            'current_cost' => '',
            'cif_percentage' => '10',
            'current_price' => '',
            'price_threshold' => '3',
            'quality_viscosity_lower' => '90',
            'quality_viscosity_upper' => '110',
            'quality_fineness_lower' => '5',
            'quality_fineness_upper' => '9',
            'quality_solids_lower' => '45',
            'quality_solids_upper' => '55',
            'is_active' => true,
        ])
        ->assertRedirect(route('products.index'));

    $this->product->refresh();

    expect($this->product->brand)->toBe('PINTECH')
        ->and($this->product->description)->toBe('Texto de descripción')
        ->and((float) $this->product->quality_viscosity_lower)->toBe(90.0)
        ->and((float) $this->product->quality_viscosity_upper)->toBe(110.0)
        ->and((float) $this->product->quality_fineness_lower)->toBe(5.0)
        ->and((float) $this->product->quality_fineness_upper)->toBe(9.0)
        ->and((float) $this->product->quality_solids_lower)->toBe(45.0)
        ->and((float) $this->product->quality_solids_upper)->toBe(55.0);
});
