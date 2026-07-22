<?php

namespace Database\Factories;

use App\Models\FinishedInventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinishedInventory>
 */
class FinishedInventoryFactory extends Factory
{
    protected $model = FinishedInventory::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'warehouse_id' => Warehouse::factory(),
            'quantity' => $this->faker->randomFloat(2, 1, 500),
        ];
    }
}
