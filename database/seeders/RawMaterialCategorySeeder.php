<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RawMaterialType;
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
                'type' => RawMaterialType::Chemical,
            ],
            [
                'code' => 'ENV-METAL',
                'name' => 'Envases Metálicos',
                'description' => 'Cuñetes, galones, tambores y otros recipientes metálicos para productos terminados',
                'type' => RawMaterialType::Container,
            ],
            [
                'code' => 'ENV-PLAST',
                'name' => 'Envases Plásticos',
                'description' => 'Galones, bidones, tambores y otros recipientes plásticos',
                'type' => RawMaterialType::Container,
            ],
            [
                'code' => 'ETIQUETAS',
                'name' => 'Etiquetas',
                'description' => 'Etiquetas con el diseño del producto que se pegan al envase',
                'type' => RawMaterialType::Label,
            ],
            [
                'code' => 'BANDEJAS',
                'name' => 'Bandejas',
                'description' => 'Bandejas para el termoencogido',
                'type' => RawMaterialType::SecondaryPackaging,
            ],
            [
                'code' => 'BOLSAS',
                'name' => 'Bolsas',
                'description' => 'Bolsas para el termoencogido',
                'type' => RawMaterialType::SecondaryPackaging,
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
