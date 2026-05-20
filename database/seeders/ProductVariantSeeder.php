<?php

namespace Database\Seeders;

use App\Enums\ComponentSystem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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
        foreach ($products as $product) {
            // Create a few variants for each product
            $presentations = [
                ['value' => 1, 'label' => 'Galón', 'sku_suffix' => 'GL'],
                ['value' => 5, 'label' => 'Cubeta', 'sku_suffix' => 'CU'],
                ['value' => 0.25, 'label' => 'Cuarto', 'sku_suffix' => 'Q'],
            ];

            foreach ($presentations as $presentation) {
                $sku = $product->id.'-'.$presentation['sku_suffix'].'-'.strtoupper(Str::random(4));

                ProductVariant::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'sku' => $sku,
                    ],
                    [
                        'unit_of_measure_id' => $unitGl?->id ?? 1,
                        'presentation_value' => $presentation['value'],
                        'presentation_label' => $presentation['label'],
                        'color' => 'Blanco', // Defaulting to Blanco for seeding
                        'finish' => 'Brillante',
                        'base_type' => 'Solvente',
                        'component_system' => ComponentSystem::OneK,
                        'package_raw_material_id' => $packagingMaterials->random()->id ?? null,
                        'is_active' => true,
                    ]
                );
                $count++;
            }
        }

        $this->command->info("Created/Updated {$count} product variants.");
    }
}
