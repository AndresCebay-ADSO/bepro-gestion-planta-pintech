<?php

namespace Database\Factories;

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Models\Alert;
use App\Models\RawMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    protected $model = Alert::class;

    public function definition(): array
    {
        return [
            'type' => AlertType::StockBajo,
            'raw_material_id' => RawMaterial::factory(),
            'batch_id' => null,
            'severity' => AlertSeverity::Media,
            'message' => $this->faker->sentence(),
            'is_resolved' => false,
            'resolved_by' => null,
            'resolved_at' => null,
            'updated_by' => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (): array => [
            'is_resolved' => true,
            'resolved_at' => now(),
        ]);
    }
}
