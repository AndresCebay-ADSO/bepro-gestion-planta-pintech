<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesOrderItem>
 */
class SalesOrderItemFactory extends Factory
{
    protected $model = SalesOrderItem::class;

    public function definition(): array
    {
        $product = Product::inRandomOrder()->first() ?? Product::factory()->create();
        $productId = $product->id;

        return [
            'sales_order_id' => SalesOrder::factory(),
            'product_id' => $productId,
            'product_variant_id' => ProductVariant::where('product_id', $productId)
                ->inRandomOrder()
                ->first()
                ?->id,
            'quantity' => $this->faker->randomFloat(4, 1, 1000),
        ];
    }
}
