<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->category = ProductCategory::create(['name' => 'Industrial']);
    $this->uom = UnitOfMeasure::create(['code' => 'GAL', 'name' => 'Galón', 'symbol' => 'gal']);
    $this->product = Product::create([
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
            'name' => 'Producto prueba calidad',
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
            'name' => 'Producto prueba calidad',
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
            'quality_fineness_upper' => '8',
            'quality_solids_lower' => '45',
            'quality_solids_upper' => '55',
            'is_active' => true,
        ])
        ->assertRedirect(route('products.index'));

    $this->product->refresh();

    expect($this->product->description)->toBe('Texto de descripción')
        ->and((float) $this->product->quality_viscosity_lower)->toBe(90.0)
        ->and((float) $this->product->quality_viscosity_upper)->toBe(110.0)
        ->and((float) $this->product->quality_fineness_lower)->toBe(5.0)
        ->and((float) $this->product->quality_fineness_upper)->toBe(8.0)
        ->and((float) $this->product->quality_solids_lower)->toBe(45.0)
        ->and((float) $this->product->quality_solids_upper)->toBe(55.0);
});

/**
 * Cuerpo válido para editar el producto, con un rango de calidad cambiado.
 *
 * @return array<string, mixed>
 */
function qualityRangePayload(object $t, array $override = []): array
{
    return array_replace([
        'name' => 'Producto prueba calidad',
        'description' => null,
        'category_id' => $t->category->id,
        'unit_of_measure_id' => $t->uom->id,
        'cif_percentage' => '10',
        'price_threshold' => '3',
        'quality_viscosity_lower' => '',
        'quality_viscosity_upper' => '',
        'quality_fineness_lower' => '',
        'quality_fineness_upper' => '',
        'quality_solids_lower' => '',
        'quality_solids_upper' => '',
        'is_active' => true,
    ], $override);
}

// PRD-01: la columna es numeric(4,1). Con dos decimales la base redondeaba sin avisar (99,99 → 100,0).
test('los rangos de calidad llevan un decimal y el tope de cada medición', function (string $field, string $value, bool $valid): void {
    $response = actingAs($this->user)
        ->put(route('products.update', $this->product), qualityRangePayload($this, [$field => $value]));

    if ($valid) {
        $response->assertSessionHasNoErrors();
        expect(bccomp((string) $this->product->fresh()->{$field}, $value, 1))->toBe(0);
    } else {
        $response->assertSessionHasErrors($field);
    }
})->with([
    'viscosidad con un decimal' => ['quality_viscosity_upper', '99.9', true],
    'viscosidad con dos decimales' => ['quality_viscosity_upper', '99.99', false],
    'viscosidad por encima de 999,9' => ['quality_viscosity_upper', '1000', false],
    'Hegman con medio punto' => ['quality_fineness_upper', '7.5', true],
    'Hegman por encima de 8' => ['quality_fineness_upper', '9', false],
    'sólidos con un decimal' => ['quality_solids_upper', '52.5', true],
    'sólidos con dos decimales' => ['quality_solids_upper', '52.55', false],
    'sólidos por encima de 100' => ['quality_solids_upper', '100.1', false],
]);

test('al crear un producto los rangos de calidad siguen la misma regla', function (): void {
    actingAs($this->user)
        ->post(route('products.store'), qualityRangePayload($this, ['name' => 'Producto nuevo calidad', 'quality_viscosity_lower' => '85.55']))
        ->assertSessionHasErrors('quality_viscosity_lower');
});
