<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company(),
            'nit' => $this->faker->unique()->numerify('9########'),
            'contact_name' => $this->faker->name(),
            'phone' => $this->faker->numerify('3#########'),
            'email' => $this->faker->unique()->safeEmail(),
            'notes' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
