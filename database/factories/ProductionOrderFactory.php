<?php

namespace Database\Factories;

use App\Enums\ProductionOrderStatus;
use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionOrder>
 */
class ProductionOrderFactory extends Factory
{
    protected $model = ProductionOrder::class;

    public function definition(): array
    {
        return [
            'order_number' => 'OP-'.fake()->unique()->numerify('2026-####'),
            'lot_number' => fake()->unique()->numberBetween(100, 99999),
            'product_id' => Product::factory(),
            'formula_id' => fn (array $attributes) => Formula::factory()->create([
                'product_id' => $attributes['product_id'],
            ]),
            'warehouse_id' => Warehouse::factory()->factory(),
            'quantity' => 100,
            'status' => ProductionOrderStatus::Completed,
            'planned_date' => now()->toDateString(),
            'created_by' => User::factory(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => ProductionOrderStatus::Pending]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (): array => ['status' => ProductionOrderStatus::InProgress]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => ['status' => ProductionOrderStatus::Completed]);
    }
}
