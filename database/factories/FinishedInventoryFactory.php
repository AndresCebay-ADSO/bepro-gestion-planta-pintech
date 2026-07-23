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
            'product_variant_id' => ProductVariant::factory(),
            'product_id' => fn (array $attributes): int => ProductVariant::query()->find($attributes['product_variant_id'])?->product_id ?? Product::factory()->create()->id,
            'warehouse_id' => Warehouse::factory(),
            'quantity' => $this->faker->randomFloat(2, 1, 500),
        ];
    }
}
