<?php

declare(strict_types=1);

return [

    'brand' => [
        'website' => 'www.beprocoatings.com',
        'website_url' => 'https://beprocoatings.com',
    ],

    // Logos del PDF: copias pequeñas y sin transparencia. DomPDF sin Imagick procesa el canal alfa píxel por píxel
    // con GD y un logo grande agota la memoria del servidor (128 MB en el contenedor).
    'pdf_logos' => [
        'header' => 'images/logo-bepro-pdf.png',
        'footer' => 'images/logo-pintech-pdf.png',
    ],

    'footer_offices' => [
        [
            'label' => 'SEDE CALI',
            'address' => 'Calle 23 No. 17-100',
            'city' => 'Valle del Cauca',
            'phones' => ['(602) 308 7767', '317 404 19 93'],
        ],
        [
            'label' => 'SEDE NEIVA',
            'address' => 'Calle 4 No. 2-06',
            'city' => 'Huila',
            'phones' => ['(608) 872 0711', '318 875 7659'],
        ],
    ],

    'legal_notes' => [
        'Esta oferta se rige por las condiciones generales de venta de Pintech; los consumos estimados en esta oferta son un cálculo aproximado según la información suministrada por el cliente, pueden existir ligeras variaciones según cada caso y condiciones de aplicación.',
        'Para maximizar el desempeño y la durabilidad del sistema recomendado, se sugiere mantener acompañamiento permanente con el asesor comercial y el equipo técnico de Bepro Coatings durante las etapas de aplicación y puesta en servicio.',
    ],

    'start_number' => (int) env('QUOTATION_START_NUMBER', 1),

];
