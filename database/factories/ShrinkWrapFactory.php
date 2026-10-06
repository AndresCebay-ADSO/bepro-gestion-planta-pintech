<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProductionOrderStatus;
use App\Models\ProductionOrder;
use App\Models\ShrinkWrap;
use App\Models\ShrinkWrapType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShrinkWrap>
 */
class ShrinkWrapFactory extends Factory
{
    protected $model = ShrinkWrap::class;

    public function definition(): array
    {
        return [
            'production_order_id' => ProductionOrder::factory()->state(['status' => ProductionOrderStatus::Completed]),
            'shrink_wrap_type_id' => ShrinkWrapType::factory(),
            'warehouse_id' => fn (array $attributes): int => (int) ProductionOrder::query()->whereKey($attributes['production_order_id'])->value('warehouse_id'),
            'applications' => 1,
            'wrapped_at' => now()->toDateString(),
            'total_cost' => '0.0000',
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }
}
