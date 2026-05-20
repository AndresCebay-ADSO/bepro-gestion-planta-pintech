<?php

namespace Database\Factories;

use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitOfMeasure>
 */
class UnitOfMeasureFactory extends Factory
{
    protected $model = UnitOfMeasure::class;

    public function definition(): array
    {
        $code = strtoupper($this->faker->unique()->lexify('U??'));

        return [
            'code' => $code,
            'name' => 'Unidad '.$code,
            'symbol' => strtolower($code),
            'description' => $this->faker->optional()->sentence(),
            'to_kg_conversion' => null,
            'to_liter_conversion' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
