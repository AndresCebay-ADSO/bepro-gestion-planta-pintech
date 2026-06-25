<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use Faker\Factory;
use Illuminate\Database\Seeder;

class ProductVariantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = Product::all();
        $unitGl = UnitOfMeasure::where('symbol', 'gl')->first();

        $packagingMaterials = RawMaterial::whereHas('category', function ($query) {
            $query->whereIn('code', ['ENV-METAL', 'ENV-PLAST']);
        })->get();

        if ($products->isEmpty()) {
            $this->command->warn('No products found. Please run ProductSeeder first.');

            return;
        }

        $count = 0;
        $faker = Factory::create();

        foreach ($products as $product) {
            $presentations = [
                ['value' => 1, 'label' => 'Galón'],
                ['value' => 3, 'label' => 'Cuñete 3G'],
                ['value' => 4, 'label' => 'Cuñete 4G'],
                ['value' => 5, 'label' => 'Cuñete 5G'],
            ];

            foreach ($presentations as $presentation) {
                ProductVariant::firstOrCreate(
                    [
                        'product_id' => $product->id,
                        'presentation_label' => $presentation['label'],
                    ],
                    [
                        'code' => $faker->unique()->numerify('########'),
                        'name' => "{$product->name} - {$presentation['label']}",
                        'unit_of_measure_id' => $unitGl?->id ?? 1,
                        'presentation_value' => $presentation['value'],
                        'package_raw_material_id' => $packagingMaterials->isNotEmpty()
                            ? $packagingMaterials->random()->id
                            : null,
                        'is_active' => true,
                    ]
                );
                $count++;
            }
        }

        $this->command->info("Created/Updated {$count} product variants.");
    }
}
