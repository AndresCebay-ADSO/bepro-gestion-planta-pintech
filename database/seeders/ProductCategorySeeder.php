<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    /**
     * Categorías del negocio Pintech basadas en los prefijos de código que usa la jefa.
     * Ej: R01, R02 → Resinas | A01, A39 → Acabados | T01 → Tintas base
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Resinas',
                'description' => 'Productos con prefijo R. Base principal para pinturas y recubrimientos.',
            ],
            [
                'name' => 'Acabados',
                'description' => 'Productos con prefijo A. Acabados finales y lacas.',
            ],
            [
                'name' => 'Tintas Base',
                'description' => 'Productos con prefijo T. Tintas base para pigmentación.',
            ],
            [
                'name' => 'Esmaltes',
                'description' => 'Productos con prefijo E. Esmaltes industriales y decorativos.',
            ],
            [
                'name' => 'Imprimantes',
                'description' => 'Productos con prefijo I. Imprimantes y fondos anticorrosivos.',
            ],
            [
                'name' => 'Selladores',
                'description' => 'Productos con prefijo S. Selladores y masillas.',
            ],
            [
                'name' => 'Diluyentes',
                'description' => 'Productos con prefijo D. Diluyentes y solventes.',
            ],
            [
                'name' => 'Otros',
                'description' => 'Productos que no corresponden a una categoría específica.',
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
