<?php

namespace Database\Factories;

use App\Models\RawMaterialCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RawMaterialCategory>
 */
class RawMaterialCategoryFactory extends Factory
{
    protected $model = RawMaterialCategory::class;

    public function definition(): array
    {
        $code = strtoupper($this->faker->unique()->bothify('CAT-###'));

        return [
            'code' => $code,
            'name' => 'Categoria '.$code,
            'description' => $this->faker->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
