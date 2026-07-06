<?php

namespace Database\Factories;

use App\Enums\PriceUpdateType;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceList>
 */
class PriceListFactory extends Factory
{
    protected $model = PriceList::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $costAtTime = $this->faker->randomFloat(4, 50, 500);
        $profitMargin = $this->faker->randomFloat(2, 5, 35);
        $price = $costAtTime * (1 + $profitMargin / 100);

        return [
            'product_id' => Product::factory(),
            'product_variant_id' => null,
            'price' => round($price, 4),
            'cost_at_time' => $costAtTime,
            'profit_margin' => $profitMargin,
            'update_type' => $this->faker->randomElement([PriceUpdateType::Manual, PriceUpdateType::Automatico]),
            'variation_percentage' => $this->faker->randomFloat(4, -5, 15),
            'valid_from' => now(),
            'valid_to' => null,
            'created_by' => User::factory(),
        ];
    }

    public function withVariant(?ProductVariant $variant = null): self
    {
        return $this->state(function () use ($variant) {
            return [
                'product_variant_id' => $variant?->id ?? ProductVariant::factory(),
            ];
        });
    }

    public function manual(): self
    {
        return $this->state(function () {
            return [
                'update_type' => PriceUpdateType::Manual,
            ];
        });
    }

    public function automatic(): self
    {
        return $this->state(function () {
            return [
                'update_type' => PriceUpdateType::Automatico,
            ];
        });
    }
}
