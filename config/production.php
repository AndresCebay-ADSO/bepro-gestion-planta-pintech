<?php

declare(strict_types=1);

return [
    'yield_tolerance' => 0.01,
    // Policies: conservative_max | weighted_average | last_lot
    // conservative_max is default to protect margin with volatile purchases.
    'raw_material_reference_price_policy' => env('RAW_MATERIAL_REFERENCE_PRICE_POLICY', 'conservative_max'),
];
