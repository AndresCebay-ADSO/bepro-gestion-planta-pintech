<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\QrCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<QrCode>
 */
class QrCodeFactory extends Factory
{
    protected $model = QrCode::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = Str::random(40);

        return [
            'product_id' => fn () => Product::first() ?? Product::create([
                'code' => 'PT-'.Str::random(5),
                'name' => 'Producto Base',
                'category_id' => ProductCategory::firstOrCreate(['name' => 'General'])->id,
                'unit_of_measure_id' => UnitOfMeasure::firstOrCreate(['code' => 'un'], ['name' => 'Unidad', 'symbol' => 'u'])->id,
                'cif_percentage' => 0,
                'price_threshold' => 0,
            ]),
            'production_order_id' => null,
            'token' => $token,
            'url' => route('qr.public.show', ['token' => $token]),
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
