<?php

declare(strict_types=1);

return [

    'brand' => [
        'website' => 'www.beprocoatings.com',
        'website_url' => 'https://beprocoatings.com',
    ],

    'footer_offices' => [
        [
            'label' => 'SEDE CALI',
            'address' => 'Calle 9 # 42-96, Bodega 12, Parque Industrial El Paraíso',
            'city' => 'Cali – Valle del Cauca',
            'phones' => ['(602) 485 0707', '310 219 2649'],
        ],
        [
            'label' => 'SEDE NEIVA',
            'address' => 'Km 3 Vía Neiva – Rivera, Bodega 8',
            'city' => 'Neiva – Huila',
            'phones' => ['(608) 871 0303', '318 390 4236'],
        ],
    ],

    'legal_notes' => [
        'Esta oferta se rige por las condiciones generales de venta de Pintech; los consumos estimados en esta oferta son un cálculo aproximado según la información suministrada por el cliente, pueden existir ligeras variaciones según cada caso y condiciones de aplicación.',
        'Para maximizar el desempeño y la durabilidad del sistema recomendado, se sugiere mantener acompañamiento permanente con el asesor comercial y el equipo técnico de Bepro Coatings durante las etapas de aplicación y puesta en servicio.',
    ],

    'start_number' => (int) env('QUOTATION_START_NUMBER', 1),

];
