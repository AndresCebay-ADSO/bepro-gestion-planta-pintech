<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'code' => $this->faker->unique()->numerify('########'),
            'name' => $this->faker->unique()->word(),
            'unit_of_measure_id' => UnitOfMeasure::where('symbol', 'gl')->first()?->id ?? UnitOfMeasure::factory(),
            'presentation_value' => $this->faker->randomElement([1, 5, 20, 0.25]),
            'presentation_label' => $this->faker->randomElement(['Galón', 'Cubeta', 'Tambor', 'Cuarto']),
            'current_cost' => $this->faker->randomFloat(4, 10, 100),
            'current_price' => $this->faker->randomFloat(4, 15, 150),
            'package_raw_material_id' => RawMaterial::whereHas('category', function ($query) {
                $query->whereIn('code', ['ENV-METAL', 'ENV-PLAST']);
            })->inRandomOrder()->first()?->id,
            'is_active' => true,
        ];
    }
}
