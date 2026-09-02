<?php

namespace Database\Factories;

use App\Models\Formula;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Formula>
 */
class FormulaFactory extends Factory
{
    protected $model = Formula::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'version' => 1,
            'is_active' => true,
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
