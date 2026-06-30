<?php

namespace Database\Factories;

use App\Enums\RemnantStatus;
use App\Models\ProductionRemnant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionRemnant>
 */
class ProductionRemnantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'original_quantity_gallons' => 10,
            'available_quantity_gallons' => 10,
            'original_quantity_kg' => 50,
            'available_quantity_kg' => 50,
            'density_kg_per_gallon' => 5,
            'status' => RemnantStatus::Available,
        ];
    }
}
