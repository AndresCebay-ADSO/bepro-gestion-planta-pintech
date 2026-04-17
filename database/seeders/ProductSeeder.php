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
        $categories = ProductCategory::all()->keyBy('name');

        // Unidad base para productos (litros - volumen de pintura)
        $literUnit = UnitOfMeasure::where('code', 'l')->first();

        $products = [
            // Esmaltes Alquídicos
            [
                'code' => 'ESM-BLA-01',
                'name' => 'Esmalte Blanco Brillante',
                'category' => 'Esmaltes Alquídicos',
                'description' => 'Esmalte sintético de alto brillo, resistente a la intemperie.',
                'is_active' => true,
            ],
            [
                'code' => 'ESM-NEG-01',
                'name' => 'Esmalte Negro Brillante',
                'category' => 'Esmaltes Alquídicos',
                'description' => 'Esmalte negro de acabado brillante para uso industrial.',
                'is_active' => true,
            ],
            [
                'code' => 'ESM-ROJ-01',
                'name' => 'Esmalte Rojo Oxido',
                'category' => 'Esmaltes Alquídicos',
                'description' => 'Esmalte anticorrosivo color rojo óxido.',
                'is_active' => true,
            ],
            [
                'code' => 'ESM-AZU-01',
                'name' => 'Esmalte Azul Industrial',
                'category' => 'Esmaltes Alquídicos',
                'description' => 'Esmalte azul de alta resistencia para maquinaria.',
                'is_active' => true,
            ],
            [
                'code' => 'ESM-GRS-01',
                'name' => 'Esmalte Gris Seguridad',
                'category' => 'Esmaltes Alquídicos',
                'description' => 'Esmalte gris para señalización y seguridad industrial.',
                'is_active' => true,
            ],

            // Pinturas de Caucho / Látex
            [
                'code' => 'LAT-BLA-01',
                'name' => 'Látex Blanco Interior',
                'category' => 'Pinturas de Caucho / Látex',
                'description' => 'Pintura látex lavable para interiores, acabado mate.',
                'is_active' => true,
            ],
            [
                'code' => 'LAT-EXT-01',
                'name' => 'Látex Exterior Resistente',
                'category' => 'Pinturas de Caucho / Látex',
                'description' => 'Pintura base agua para exteriores, resistente a la intemperie.',
                'is_active' => true,
            ],
            [
                'code' => 'CAU-NEG-01',
                'name' => 'Caucho Negro Industrial',
                'category' => 'Pinturas de Caucho / Látex',
                'description' => 'Pintura de caucho negra para pisos industriales.',
                'is_active' => true,
            ],

            // Impermeabilizantes
            [
                'code' => 'IMP-FBR-01',
                'name' => 'Impermeabilizante Fibra 5 Años',
                'category' => 'Impermeabilizantes',
                'description' => 'Recubrimiento elastomérico con fibra de vidrio, durabilidad 5 años.',
                'is_active' => true,
            ],
            [
                'code' => 'IMP-ELA-01',
                'name' => 'Impermeabilizante Elastomérico Blanco',
                'category' => 'Impermeabilizantes',
                'description' => 'Impermeabilizante acrílico elastomérico para techos.',
                'is_active' => true,
            ],
            [
                'code' => 'IMP-TEJ-01',
                'name' => 'Impermeabilizante Teja',
                'category' => 'Impermeabilizantes',
                'description' => 'Recubrimiento color teja para protección de techos.',
                'is_active' => true,
            ],

            // Pinturas Especiales / Epóxicas
            [
                'code' => 'EPX-BLA-01',
                'name' => 'Epóxica Blanca Piso',
                'category' => 'Pinturas Especiales / Epóxicas',
                'description' => 'Sistema epóxico 2 componentes para pisos industriales.',
                'is_active' => true,
            ],
            [
                'code' => 'EPX-GRS-01',
                'name' => 'Epóxica Gris Seguridad',
                'category' => 'Pinturas Especiales / Epóxicas',
                'description' => 'Pintura epóxica gris para demarcación de áreas.',
                'is_active' => true,
            ],
            [
                'code' => 'EPX-AMA-01',
                'name' => 'Epóxica Amarilla Demarcación',
                'category' => 'Pinturas Especiales / Epóxicas',
                'description' => 'Pintura epóxica amarilla para señalización de pisos.',
                'is_active' => true,
            ],
            [
                'code' => 'EPX-TRAN-01',
                'name' => 'Barniz Epóxico Transparente',
                'category' => 'Pinturas Especiales / Epóxicas',
                'description' => 'Barniz epóxico cristal para protección de superficies.',
                'is_active' => true,
            ],
            [
                'code' => 'PUR-ALT-01',
                'name' => 'Poliuretano Altsolid',
                'category' => 'Pinturas Especiales / Epóxicas',
                'description' => 'Recubrimiento poliuretano de alto sólidos para exteriores.',
                'is_active' => true,
            ],

            // Anticorrosivos
            [
                'code' => 'ANT-ZIN-01',
                'name' => 'Zincromato Industrial',
                'category' => 'Anticorrosivos',
                'description' => 'Fondo anticorrosivo con cromato de zinc.',
                'is_active' => true,
            ],
            [
                'code' => 'ANT-OXI-01',
                'name' => 'Oxido de Hierro Rojo',
                'category' => 'Anticorrosivos',
                'description' => 'Fondo anticorrosivo base óxido de hierro rojo.',
                'is_active' => true,
            ],
            [
                'code' => 'ANT-GRS-01',
                'name' => 'Fondo Gris Universal',
                'category' => 'Anticorrosivos',
                'description' => 'Fondo gris multiusos para protección de metales.',
                'is_active' => true,
            ],

            // Lacas y Acabados para Madera
            [
                'code' => 'LAC-NC-CR',
                'name' => 'Laca Nitrocelulósica Cristal',
                'category' => 'Lacas y Acabados para Madera',
                'description' => 'Laca transparente de secado rápido para madera.',
                'is_active' => true,
            ],
            [
                'code' => 'LAC-NC-SE',
                'name' => 'Laca Nitrocelulósica Semitransparente',
                'category' => 'Lacas y Acabados para Madera',
                'description' => 'Laca color madera semitransparente.',
                'is_active' => true,
            ],
            [
                'code' => 'SEL-POL-01',
                'name' => 'Sellador Poliuretano',
                'category' => 'Lacas y Acabados para Madera',
                'description' => 'Sellador base poliuretano para madera.',
                'is_active' => true,
            ],

            // Masillas y Empastes
            [
                'code' => 'MAS-POL-01',
                'name' => 'Masilla Poliéster',
                'category' => 'Masillas y Empastes',
                'description' => 'Masilla de poliéster bicomponente para automotriz.',
                'is_active' => true,
            ],
            [
                'code' => 'EMP-ACE-01',
                'name' => 'Empaste Aceite',
                'category' => 'Masillas y Empastes',
                'description' => 'Empaste base aceite para nivelación de superficies.',
                'is_active' => true,
            ],
        ];

        foreach ($products as $product) {
            $category = $categories->get($product['category']);

            Product::updateOrCreate(
                ['code' => $product['code']],
                [
                    'category_id' => $category?->id,
                    'unit_of_measure_id' => $literUnit?->id,
                    'name' => $product['name'],
                    'description' => $product['description'],
                    'is_active' => $product['is_active'],
                ]
            );
        }

        $this->command->info('Created/Updated '.Product::count().' products.');
    }
}
