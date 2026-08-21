<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'code' => 'PROD-'.$this->faker->unique()->numberBetween(1000, 9999),
            'name' => $this->faker->words(3, true),
            'brand' => 'Pintech',
            'description' => $this->faker->optional()->sentence(),
            'category_id' => ProductCategory::factory(),
            'unit_of_measure_id' => UnitOfMeasure::factory(),
            'current_cost' => 10.00,
            'cif_percentage' => 0.00,
            'sales_margin' => 30.00,
            'current_price' => 13.00,
            'price_threshold' => 5.00,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
