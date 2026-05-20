<?php

namespace Database\Factories;

use App\Enums\InventoryMovementType;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\RawMaterial;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    protected $model = InventoryMovement::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement([
            InventoryMovementType::Entry,
            InventoryMovementType::Exit,
        ]);

        return [
            'raw_material_id' => RawMaterial::factory(),
            'warehouse_id' => Warehouse::factory(),
            'batch_id' => InventoryBatch::factory(),
            'production_order_id' => null,
            'type' => $type,
            'quantity' => $this->faker->randomFloat(4, 0.0001, 100),
            'cost_price' => $this->faker->randomFloat(4, 0, 10000),
            'movement_date' => $this->faker->date(),
            'notes' => $this->faker->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function entry(): static
    {
        return $this->state(fn (): array => [
            'type' => InventoryMovementType::Entry,
        ]);
    }

    public function exit(): static
    {
        return $this->state(fn (): array => [
            'type' => InventoryMovementType::Exit,
        ]);
    }
}
