<?php

namespace Database\Factories;

use App\Models\InventoryBatch;
use App\Models\RawMaterial;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryBatch>
 */
class InventoryBatchFactory extends Factory
{
    protected $model = InventoryBatch::class;

    public function definition(): array
    {
        $initialQuantity = $this->faker->randomFloat(4, 1, 500);

        return [
            'raw_material_id' => RawMaterial::factory(),
            'warehouse_id' => Warehouse::factory(),
            'initial_quantity' => $initialQuantity,
            'remaining_quantity' => $initialQuantity,
            'unit_price' => $this->faker->randomFloat(4, 0.0001, 10000),
            'entry_date' => $this->faker->date(),
            'expiry_date' => null,
            'supplier' => $this->faker->optional()->company(),
            'lot_number' => strtoupper($this->faker->optional()->bothify('LOT-###??')),
        ];
    }

    public function exhausted(): static
    {
        return $this->state(function (array $attributes): array {
            return [
                'remaining_quantity' => 0,
            ];
        });
    }
}
