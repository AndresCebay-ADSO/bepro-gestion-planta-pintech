<?php

namespace Database\Factories;

use App\Enums\WarehouseType;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'name' => 'Bodega '.$this->faker->unique()->company(),
            'city' => $this->faker->city(),
            'address' => $this->faker->optional()->address(),
            'type' => $this->faker->randomElement([
                WarehouseType::Factory,
                WarehouseType::Storage,
            ]),
            'is_active' => true,
        ];
    }

    public function factory(): static
    {
        return $this->state(fn (): array => ['type' => WarehouseType::Factory]);
    }

    public function storage(): static
    {
        return $this->state(fn (): array => ['type' => WarehouseType::Storage]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
