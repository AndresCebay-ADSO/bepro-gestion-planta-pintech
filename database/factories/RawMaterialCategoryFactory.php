<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RawMaterialType;
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
            'type' => RawMaterialType::Chemical,
            'is_active' => true,
        ];
    }

    public function container(): static
    {
        return $this->state(['type' => RawMaterialType::Container]);
    }

    public function label(): static
    {
        return $this->state(['type' => RawMaterialType::Label]);
    }

    public function secondaryPackaging(): static
    {
        return $this->state(['type' => RawMaterialType::SecondaryPackaging]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
