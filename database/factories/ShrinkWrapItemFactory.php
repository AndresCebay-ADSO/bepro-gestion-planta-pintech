<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RawMaterial;
use App\Models\ShrinkWrap;
use App\Models\ShrinkWrapItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShrinkWrapItem>
 */
class ShrinkWrapItemFactory extends Factory
{
    protected $model = ShrinkWrapItem::class;

    public function definition(): array
    {
        return [
            'shrink_wrap_id' => ShrinkWrap::factory(),
            'raw_material_id' => RawMaterial::factory()->secondaryPackaging(),
            'quantity_per_application' => '1.0000',
            'quantity' => '1.0000',
            'total_cost' => '0.0000',
        ];
    }
}
