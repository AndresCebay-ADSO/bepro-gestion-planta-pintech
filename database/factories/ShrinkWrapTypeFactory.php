<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ShrinkWrapType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShrinkWrapType>
 */
class ShrinkWrapTypeFactory extends Factory
{
    protected $model = ShrinkWrapType::class;

    public function definition(): array
    {
        return [
            'name' => 'Termoencogido '.strtoupper($this->faker->unique()->bothify('???-###')),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
