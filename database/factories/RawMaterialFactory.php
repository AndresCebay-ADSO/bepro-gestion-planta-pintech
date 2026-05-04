<?php

namespace Database\Factories;

use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RawMaterial>
 */
class RawMaterialFactory extends Factory
{
    protected $model = RawMaterial::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('RM-###')),
            'category_id' => RawMaterialCategory::factory(),
            'unit_of_measure_id' => UnitOfMeasure::factory(),
            'current_price' => $this->faker->randomFloat(4, 0, 50000),
            'previous_price' => null,
            'minimum_stock' => $this->faker->randomFloat(4, 0, 100),
            'alert_days_before_expiry' => $this->faker->numberBetween(1, 90),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
