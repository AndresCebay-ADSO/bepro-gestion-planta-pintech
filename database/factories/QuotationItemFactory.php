<?php

namespace Database\Factories;

use App\Enums\QuotationItemType;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuotationItem>
 */
class QuotationItemFactory extends Factory
{
    protected $model = QuotationItem::class;

    public function definition(): array
    {
        $listPrice = $this->faker->randomFloat(4, 10000, 500000);
        $quantity = $this->faker->randomFloat(4, 1, 20);
        $unitPrice = $listPrice;

        return [
            'quotation_id' => Quotation::factory(),
            'product_id' => Product::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'type' => $this->faker->randomElement(QuotationItemType::cases())?->value,
            'description' => $this->faker->sentence(4),
            'color' => $this->faker->optional()->safeColorName(),
            'quantity' => $quantity,
            'list_unit_price' => $listPrice,
            'price_adjustment_pct' => 0,
            'unit_price' => $unitPrice,
            'subtotal' => round($quantity * $unitPrice, 4),
            'sort_order' => 1,
        ];
    }
}
