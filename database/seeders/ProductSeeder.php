<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;

/**
 * Seeder for Product model.
 * Crea productos terminados (pinturas) para Pintech.
 */
class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $category = ProductCategory::first();
        $unit = UnitOfMeasure::where('symbol', 'gl')->first();

        Product::updateOrCreate(
            ['code' => 'IE - 400 FM'],
            [
                'category_id' => $category?->id,
                'unit_of_measure_id' => $unit?->id,
                'name' => 'AJUSTADOR EPOXICO IE - 400 FM',
                'description' => 'Ajustador epóxico',
                'is_active' => true,
            ]
        );

        $this->command->info('Created/Updated 1 product: IE - 400 FM');
    }
}
