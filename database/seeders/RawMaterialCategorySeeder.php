<?php

namespace Database\Seeders;

use App\Models\RawMaterialCategory;
use Illuminate\Database\Seeder;

class RawMaterialCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'code' => 'QUIMICOS',
                'name' => 'Químicos y Reactivos',
                'description' => 'Pigmentos, resinas, solventes, aditivos y otros componentes químicos',
            ],
            [
                'code' => 'ENV-METAL',
                'name' => 'Envases Metálicos',
                'description' => 'Cuñetes, galones, tambores y otros recipientes metálicos para productos terminados',
            ],
            [
                'code' => 'ENV-PLAST',
                'name' => 'Envases Plásticos',
                'description' => 'Galones, bidones, tambores y otros recipientes plásticos',
            ],
            [
                'code' => 'TAPAS',
                'name' => 'Tapas y Accesorios',
                'description' => 'Tapas flex, tapas rosca, asas y accesorios de cierre',
            ],
            [
                'code' => 'ETIQUETAS',
                'name' => 'Etiquetas y Empaque',
                'description' => 'Etiquetas adhesivas, cintas, material de empaque',
            ],
        ];

        foreach ($categories as $category) {
            RawMaterialCategory::updateOrCreate(
                ['code' => $category['code']],
                array_merge($category, [
                    'is_active' => true,
                ])
            );
        }

        $this->command->info('Created/Updated '.RawMaterialCategory::count().' raw material categories.');
    }
}
