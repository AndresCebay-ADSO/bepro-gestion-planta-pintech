<?php

namespace Database\Factories;

use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductionCost;
use App\Models\ProductionOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionCost>
 */
class ProductionCostFactory extends Factory
{
    protected $model = ProductionCost::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'formula_id' => Formula::factory(),
            'production_order_id' => null,
            'cost' => $this->faker->randomFloat(4, 50, 500),
            'unit_cost' => $this->faker->randomFloat(4, 10, 100),
            'variation_percentage' => $this->faker->randomFloat(4, -5, 15),
            'calculated_at' => now(),
        ];
    }

    public function forProductionOrder(?ProductionOrder $order = null): self
    {
        return $this->state(function () use ($order) {
            return [
                'production_order_id' => $order?->id ?? ProductionOrder::factory(),
            ];
        });
    }

    public function withNoVariation(): self
    {
        return $this->state(function () {
            return [
                'variation_percentage' => 0,
            ];
        });
    }
}
