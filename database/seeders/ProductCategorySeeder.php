<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    /**
     * Categorías del negocio Pintech para PRODUCTOS TERMINADOS (Pinturas).
     * Los códigos de prefijos puros corresponden a materias primas, no productos comerciales.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Esmaltes Alquídicos',
                'description' => 'Esmaltes sintéticos base solvente para protección y decoración de metales y maderas.',
            ],
            [
                'name' => 'Pinturas de Caucho / Látex',
                'description' => 'Pinturas base agua emulsionadas para interiores y exteriores.',
            ],
            [
                'name' => 'Impermeabilizantes',
                'description' => 'Recubrimientos elastoméricos para protección contra humedad e impermeabilización de techos y muros.',
            ],
            [
                'name' => 'Pinturas Especiales / Epóxicas',
                'description' => 'Sistemas epóxicos y poliuretanos para alta resistencia industrial y pisos.',
            ],
            [
                'name' => 'Anticorrosivos',
                'description' => 'Fondos y bases para protección inicial de superficies metálicas contra la oxidación.',
            ],
            [
                'name' => 'Pinturas en Polvo',
                'description' => 'Recubrimientos horneables sin solventes para acabados industriales.',
            ],
            [
                'name' => 'Lacas y Acabados para Madera',
                'description' => 'Lacas nitrocelulósicas, catalizadas y selladores para la industria maderera.',
            ],
            [
                'name' => 'Masillas y Empastes',
                'description' => 'Productos para preparación y nivelación de superficies.',
            ],
        ];

        foreach ($categories as $category) {
            ProductCategory::firstOrCreate(
                ['name' => $category['name']],
                ['description' => $category['description']]
            );
        }
    }
}
