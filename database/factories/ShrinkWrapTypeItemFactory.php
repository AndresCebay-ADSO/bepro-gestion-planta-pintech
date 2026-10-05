<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RawMaterial;
use App\Models\ShrinkWrapType;
use App\Models\ShrinkWrapTypeItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShrinkWrapTypeItem>
 */
class ShrinkWrapTypeItemFactory extends Factory
{
    protected $model = ShrinkWrapTypeItem::class;

    public function definition(): array
    {
        return [
            'shrink_wrap_type_id' => ShrinkWrapType::factory(),
            'raw_material_id' => RawMaterial::factory()->secondaryPackaging(),
            'quantity' => '1.0000',
        ];
    }
}
